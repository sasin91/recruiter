<?php
require_once __DIR__ . '/Unigram.php';

/**
 * The static embedding model (Model2Vec, potion-multilingual-128M pruned to
 * Danish and English job text): tokenize, look up one int8 row per token,
 * average, normalise. It is a table lookup, so no ML runtime is needed.
 *
 * Files, in taxonomy/models/potion-multilingual-da/:
 *   model.json         { normalizer, unk_id, unk_score, dim }
 *   vocab.tsv          one `piece<TAB>score` per line, line number = id
 *   embeddings.q8.bin  [rows x f32 scale][rows x dim x i8], little-endian
 *
 * The tokenizer must agree with Hugging Face's on every input the index was
 * built from; tokenizer-fixture.json is checked by tests.php.
 */
class Static_model {

    public int $dim;
    public Unigram $unigram;
    private string $normalizer;
    private int $rows;

    /** @var string embeddings.q8.bin as read; rows are unpacked on demand. */
    private string $buffer;

    /** Whitespace as JavaScript's \s defines it, which Model2Vec's Python agrees with. */
    private const WHITESPACE = '\t\n\x{0B}\f\r \x{A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

    public function __construct(array $meta, string $vocab, string $buffer) {
        $pieces = self::parse_vocab($vocab);
        $rows = count($pieces);
        $dim = intdiv(strlen($buffer) - $rows * 4, $rows);
        if ($dim !== (int) $meta['dim'] || $rows * 4 + $rows * $dim !== strlen($buffer)) {
            throw new RuntimeException('model file of ' . strlen($buffer) . " bytes does not fit $rows tokens x {$meta['dim']}");
        }
        if ($meta['normalizer'] !== 'nmt_nfkc') {
            throw new RuntimeException("unsupported normalizer {$meta['normalizer']}");
        }
        $this->dim = $dim;
        $this->rows = $rows;
        $this->normalizer = $meta['normalizer'];
        $this->unigram = new Unigram($pieces, (int) $meta['unk_id'], (float) $meta['unk_score']);
        $this->buffer = $buffer;
    }

    /** Loads the model from its directory. */
    public static function load(string $dir): self {
        $meta = json_decode(self::read("$dir/model.json"), true, 512, JSON_THROW_ON_ERROR);
        return new self($meta, self::read("$dir/vocab.tsv"), self::read("$dir/embeddings.q8.bin"));
    }

    /**
     * Parses vocab.tsv: one `piece<TAB>score` per line, line number = id.
     *
     * @return array<int, array{0: string, 1: float}>
     */
    public static function parse_vocab(string $text): array {
        $lines = explode("\n", $text);
        if (end($lines) === '') {
            array_pop($lines);
        }
        $pieces = [];
        foreach ($lines as $line) {
            $tab = strrpos($line, "\t");
            $pieces[] = [substr($line, 0, $tab), (float) substr($line, $tab + 1)];
        }
        return $pieces;
    }

    /**
     * SentencePiece's nmt_nfkc charsmap, as Hugging Face's tokenizers.js
     * approximates it: drop control characters, turn odd spaces into a plain
     * space, then NFKC. NFKC leaves æ, ø and å alone.
     */
    public static function nmt_nfkc(string $text): string {
        $text = mb_scrub($text, 'UTF-8');
        $text = preg_replace('/[\x{01}-\x{08}\x{0B}\x{0E}-\x{1F}\x{7F}\x{8F}\x{9F}]/u', '', $text);
        $text = preg_replace('/[\x{09}\x{0A}\x{0C}\x{0D}\x{A0}\x{1680}\x{2000}-\x{200F}\x{2028}\x{2029}\x{202F}\x{205F}\x{2581}\x{3000}\x{FEFF}\x{FFFD}]/u', ' ', $text);
        return Normalizer::normalize($text, Normalizer::FORM_KC);
    }

    /**
     * The text exactly as the Unigram model sees it, with U+2581 for spaces.
     * Model2Vec puts a space around every ASCII punctuation mark before the
     * tokenizer sees the text, then collapses runs of whitespace.
     */
    public static function prepare(string $text): string {
        $out = preg_replace('/ {2,}/', ' ', self::nmt_nfkc($text));
        $out = preg_replace('/([!"#$%&\'()*+,\-.\/:;<=>?@[\\\\\]^_`{|}~])/', ' $1 ', $out);
        $out = trim(preg_replace('/[' . self::WHITESPACE . ']+/u', ' ', $out), ' ');
        return $out === '' ? '' : "\u{2581}" . str_replace(' ', "\u{2581}", $out);
    }

    /**
     * Lowercases capitalised words ("Regnskab", "Projektleder") and leaves
     * acronyms and mixed case alone ("SAP", "IT", "iOS", "JavaScript"), so a
     * label's capital first letter does not change its meaning to the model.
     * A capitalised word straight after a letter is part of a CamelCase word
     * and is kept.
     */
    public static function fold_case(string $text): string {
        return preg_replace_callback(
            '/(?<!\p{L})\p{Lu}[\p{Ll}\p{M}]+(?![\p{L}\p{M}])/u',
            fn(array $m) => mb_strtolower($m[0], 'UTF-8'),
            $text
        );
    }

    /**
     * Token strings of `text`, including any [UNK].
     *
     * @return string[]
     */
    public function tokenize(string $text): array {
        $ids = $this->unigram->encode_ids(self::prepare($text));
        return array_map(fn(int $id) => $this->unigram->pieces[$id], $ids);
    }

    /**
     * Mean of the known tokens' rows, normalised to unit length. Unknown
     * tokens are dropped, as Model2Vec does. `vector` is null when no token
     * was known, since a zero vector is similar to nothing.
     *
     * The text is case-folded first (see fold_case()): the multilingual
     * model is cased, and "Regnskab" and "regnskab" would otherwise sit at
     * cosine 0.25.
     *
     * @return array{tokens: string[], unknown: int, vector: ?float[]}
     */
    public function encode(string $text): array {
        $ids = $this->unigram->encode_ids(self::prepare(self::fold_case($text)));
        $sum = array_fill(0, $this->dim, 0.0);
        $known = 0;
        foreach ($ids as $id) {
            if ($id === $this->unigram->unk_id) {
                continue;
            }
            $scale = unpack('g', $this->buffer, $id * 4)[1];
            $row = unpack("c{$this->dim}", $this->buffer, $this->rows * 4 + $id * $this->dim);
            for ($i = 0; $i < $this->dim; $i++) {
                $sum[$i] += $row[$i + 1] * $scale;
            }
            $known++;
        }
        return [
            'tokens' => array_map(fn(int $id) => $this->unigram->pieces[$id], $ids),
            'unknown' => count($ids) - $known,
            'vector' => $known ? self::unit($sum) : null,
        ];
    }

    /**
     * @param float[] $vector
     * @return float[]
     */
    public static function unit(array $vector): array {
        $length = 0.0;
        foreach ($vector as $v) {
            $length += $v * $v;
        }
        $length = sqrt($length);
        if ($length > 0) {
            foreach ($vector as $i => $v) {
                $vector[$i] = $v / $length;
            }
        }
        return $vector;
    }

    /** Cosine similarity of two unit vectors, which is just their dot product. */
    public static function cosine(array $a, array $b): float {
        $dot = 0.0;
        foreach ($a as $i => $v) {
            $dot += $v * $b[$i];
        }
        return $dot;
    }

    private static function read(string $path): string {
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException("cannot read $path");
        }
        return $data;
    }

}
