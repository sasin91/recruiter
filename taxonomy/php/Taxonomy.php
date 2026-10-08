<?php
require_once __DIR__ . '/Static_model.php';

/**
 * Taxonomy lookup for the recruiter: turns a phrase from a job post or an
 * application ("Erfaring med SAP", "projektleder", "Kørekort B") into ranked
 * skill, role and title terms. Plain PHP, no framework dependency, so it runs
 * from the CLI (the tests in tests/) and from any Trongate module alike:
 *
 *   $taxonomy = Taxonomy::load();
 *   $results = $taxonomy->search('Erfaring med SAP', 'skill');
 *   Taxonomy::match_method($results[0], 'Erfaring med SAP'); // 'exact'
 *
 * Ported from taxonomy.mjs (itself from sasin91/js/retrieval.mjs). Two
 * independent signals are combined:
 *
 *   semantic  The static embedding model (Static_model). build-index.php ran
 *             the same steps on every term label, so a query and a term land
 *             in the same vector space.
 *
 *   lexical   Plain word matching against each term's labels and definition,
 *             so that exact names like "SAP" or "Kørekort B" are never
 *             outranked by something vaguely similar in meaning. Words also
 *             match with one or two typos ("projektleeder"), which is what
 *             step 3 of the pitch calls typo tolerance.
 *
 * Danish specifics: æ, ø and å survive normalisation, text is NFC-normalised
 * before word matching (a decomposed "å" from a PDF matches), and Danish
 * stopwords join the English ones. "it" is not a stopword, because "IT" is a
 * skill word in Danish ("IT-support", "IT-chef").
 */
class Taxonomy {

    /**
     * How much each kind of evidence is worth. A whole label of the term
     * appearing in the query is the strongest signal there is; a word turning
     * up in the definition is the weakest.
     */
    public const WEIGHTS = [
        'label' => 1.0,    // the term's preferred label (da or en), as a phrase
        'synonym' => 0.9,  // another label of the term, as a phrase
        'keyword' => 0.25, // a word from any label
        'text' => 0.1,     // a word from the definition
        'typo' => 0.8,     // multiplier for a word matched with typos
        // How far lexical evidence can move a result relative to cosine
        // similarity. Cosines between unrelated phrases of a static model
        // still sit around 0.2-0.4, so an exact label hit (lexical around
        // 0.6) is meant to clear that gap.
        'lexical' => 0.5,
    ];

    // Words that say nothing about which term a phrase is about. Kept short
    // on purpose: a word missing from here only adds a little noise, while a
    // skill word wrongly listed here could never be matched. English "it" and
    // Danish "it" are deliberately absent ("IT-support"), and so is "c" ("C#").
    private const STOPWORDS =
        // English
        'a about after all also an and any are as at be because been before but by can could ' .
        'did do does doesn don each for from had has have how i if in into is isn its just ' .
        'me more most my no not of on or other our out over s so some such t than that the their ' .
        'them then there these they this those through to too under up us very was way we were ' .
        'what when where which while who why will with would you your ' .
        // Danish (the Snowball list, minus words that are also skill words)
        'ad af alle alt anden at blev blive bliver da de dem den denne der deres det dette dig din ' .
        'disse dog du efter eller en end er et for fra ham han hans har havde have hende hendes her ' .
        'hos hun hvad hvis hvor i ikke ind jeg jer jo kunne man mange med meget men mig min mine mit ' .
        'mod ned noget nogle nu når og også om op os over på sig sin sine sit skal skulle som sådan ' .
        'thi til ud under var vi vil ville vor være været';

    // Words job ads wrap around a skill ("erfaring med SAP", "strong
    // knowledge of Python"). They are dropped from the query only, never from
    // the terms, so a term like "Erfaringsudveksling" is unaffected.
    private const QUALIFIERS =
        'erfaring erfaringer kendskab viden god gode godt stærk stærke solid solide indgående ' .
        'grundlæggende evne evner evnen års år gerne flere ' .
        'experience experienced knowledge strong good solid proven ability years year basic advanced';

    /** @var array<string, true>|null */
    private static ?array $stopwords = null;

    /** @var array<string, true>|null */
    private static ?array $qualifiers = null;

    /** @var array[] prepared terms, in index order (row = position) */
    public array $terms;

    /** @var array<string, int> word => number of terms it appears in */
    private array $frequency = [];

    /** @var array<int, string[]> index words by length, for typo lookups */
    private array $by_length = [];

    /** @var float[] row scale of the int8 term matrix */
    private array $scales = [];

    /** int8 term matrix, [rows x dim], unpacked a row at a time */
    private string $weights = '';

    private int $dim = 0;

    /**
     * Prepares a static index (written by build-index.php) for ranking:
     * phrase lists, word sets, document frequencies and the vectors.
     *
     * @param array $index { dim, terms: [{ id, kind, labels: [{ text, lang, preferred }], definition? }] }
     * @param ?string $vectors index.q8.bin's contents, or null to rank lexically only
     * @param ?Static_model $model needed by search(); rank() takes a vector instead
     */
    public function __construct(array $index, ?string $vectors = null, public ?Static_model $model = null) {
        $this->terms = [];
        foreach ($index['terms'] as $row => $term) {
            $phrases = [];
            $keywords = [];
            foreach ($term['labels'] as $label) {
                $words = self::words($label['text']);
                if (!array_filter($words, fn(string $w) => !self::is_stopword($w))) {
                    continue;
                }
                $phrases[] = [
                    'text' => $label['text'],
                    'lang' => $label['lang'],
                    'field' => $label['preferred'] ? 'label' : 'synonym',
                    'words' => $words,
                ];
                foreach ($words as $w) {
                    $keywords[$w] = true;
                }
            }
            $text = array_fill_keys(self::words($term['definition'] ?? ''), true);
            $this->terms[] = $term + ['row' => $row, 'phrases' => $phrases, 'keywords' => $keywords, 'text' => $text];
            foreach (array_keys($keywords + $text) as $word) {
                $word = (string) $word; // numeric words come back as int keys
                $this->frequency[$word] = ($this->frequency[$word] ?? 0) + 1;
            }
        }

        foreach (array_keys($this->frequency) as $word) {
            $word = (string) $word;
            $this->by_length[mb_strlen($word, 'UTF-8')][] = $word;
        }

        if ($vectors !== null) {
            $rows = count($this->terms);
            $dim = (int) $index['dim'];
            if (strlen($vectors) !== $rows * 4 + $rows * $dim) {
                throw new RuntimeException('vector file of ' . strlen($vectors) . " bytes does not fit $rows terms x $dim");
            }
            $this->dim = $dim;
            $this->scales = array_values(unpack("g$rows", $vectors));
            $this->weights = substr($vectors, $rows * 4);
        }
    }

    /**
     * The shipped index (taxonomy/data) and model (taxonomy/models), or
     * another build of them.
     */
    public static function load(?string $data_dir = null, ?string $model_dir = null): self {
        $root = dirname(__DIR__);
        $data_dir ??= "$root/data";
        $model_dir ??= "$root/models/potion-multilingual-da";
        $index = json_decode(self::read("$data_dir/index.json"), true, 512, JSON_THROW_ON_ERROR);
        $model = Static_model::load($model_dir);
        return new self($index, self::read("$data_dir/{$index['vectors']}"), $model);
    }

    /**
     * Encodes the query with the model and ranks every term for it.
     *
     * @return array[] see rank()
     */
    public function search(string $query, ?string $kind = null, int $limit = 5): array {
        $vector = $this->model?->encode($query)['vector'];
        return $this->rank($query, $vector, $kind, $limit);
    }

    /**
     * Ranks every term for `query`, optionally of one `kind` (skill, role,
     * title).
     *
     * With a query vector: score = cosine + WEIGHTS['lexical'] x lexical.
     * Without one (no model, or no known tokens): score = lexical.
     *
     * Each result carries the evidence behind it, for the "why this matched"
     * view and for job_post_terms.match_method: the cosine, the rank by
     * meaning alone, and the matched labels and words.
     *
     * @param ?float[] $query_vector
     * @return array<int, array{term: array, score: float, semantic: ?float, semantic_rank?: int, lexical: float, matched: array[]}>
     */
    public function rank(string $query, ?array $query_vector, ?string $kind = null, int $limit = 5): array {
        $expanded = $this->expand_query($query);
        $query_words = self::words($query);
        $content = self::content_words($query);
        $use_vectors = $query_vector !== null && $this->weights !== '';
        $scored = [];
        foreach ($this->terms as $term) {
            if ($kind !== null && $term['kind'] !== $kind) {
                continue;
            }
            $lexical = $this->lexical_match($term, $query, $expanded, $query_words, $content);
            $semantic = $use_vectors ? $this->term_cosine($term['row'], $query_vector) : null;
            $scored[] = [
                'term' => $term,
                'score' => $use_vectors ? $semantic + self::WEIGHTS['lexical'] * $lexical['score'] : $lexical['score'],
                'semantic' => $semantic,
                'lexical' => $lexical['score'],
                'matched' => $lexical['matched'],
            ];
        }

        if ($use_vectors) {
            $order = array_keys($scored);
            usort($order, fn(int $a, int $b) => $scored[$b]['semantic'] <=> $scored[$a]['semantic']);
            foreach ($order as $i => $key) {
                $scored[$key]['semantic_rank'] = $i + 1;
            }
        }

        $scored = array_filter($scored, fn(array $r) => $r['score'] > 0);
        usort($scored, fn(array $a, array $b) => ($b['score'] <=> $a['score']) ?: ($a['term']['id'] <=> $b['term']['id']));
        return array_slice($scored, 0, $limit);
    }

    /**
     * How a query mapped onto a result, in the codes
     * job_post_terms.match_method uses: `exact` when the query (or its content
     * words: "erfaring med SAP" is "sap") is the term's preferred label,
     * `synonym` when it is another label, `fuzzy` when it is a label with
     * typos, `embedding` otherwise.
     */
    public static function match_method(array $result, string $query): string {
        $all = self::words($query);
        $content = self::content_words($query);
        foreach ($result['term']['phrases'] as $phrase) {
            if ($phrase['words'] === $all || $phrase['words'] === $content) {
                return $phrase['field'] === 'label' ? 'exact' : 'synonym';
            }
        }
        foreach ($result['matched'] as $m) {
            if ($m['typos'] > 0 && ($m['field'] === 'label' || $m['field'] === 'synonym')) {
                return count(self::words($m['text'])) === count($content) ? 'fuzzy' : 'embedding';
            }
        }
        return 'embedding';
    }

    // ---------- words ----------

    /**
     * Lowercase runs of letters and digits, æøå kept, NFC first.
     *
     * @return string[]
     */
    public static function words(string $text): array {
        $text = Normalizer::normalize(mb_scrub($text, 'UTF-8'), Normalizer::FORM_C);
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text, 'UTF-8'), $m);
        return $m[0];
    }

    /**
     * The words of a query worth matching on: no stopwords, no qualifiers,
     * no repeats.
     *
     * @return string[]
     */
    public static function content_words(string $text): array {
        $out = [];
        foreach (self::words($text) as $w) {
            if (!self::is_stopword($w) && !isset(self::qualifiers()[$w]) && !in_array($w, $out, true)) {
                $out[] = $w;
            }
        }
        return $out;
    }

    /**
     * Optimal string alignment distance (Levenshtein plus adjacent swaps),
     * giving up once it is certain to exceed `max`.
     */
    public static function edit_distance(string $a, string $b, int $max = 2): int {
        $s = mb_str_split($a, 1, 'UTF-8');
        $t = mb_str_split($b, 1, 'UTF-8');
        $m = count($s);
        $n = count($t);
        if (abs($m - $n) > $max) {
            return $max + 1;
        }
        $prev2 = null;
        $prev = range(0, $n);
        for ($i = 1; $i <= $m; $i++) {
            $row = [$i];
            $row_min = $i;
            for ($j = 1; $j <= $n; $j++) {
                $d = min($prev[$j] + 1, $row[$j - 1] + 1, $prev[$j - 1] + ($s[$i - 1] === $t[$j - 1] ? 0 : 1));
                if ($i > 1 && $j > 1 && $s[$i - 1] === $t[$j - 2] && $s[$i - 2] === $t[$j - 1]) {
                    $d = min($d, $prev2[$j - 2] + 1);
                }
                $row[] = $d;
                $row_min = min($row_min, $d);
            }
            if ($row_min > $max) {
                return $max + 1;
            }
            $prev2 = $prev;
            $prev = $row;
        }
        return $prev[$n];
    }

    /** How many typos a word of this length may carry and still match. */
    public static function typo_budget(string $word): int {
        $n = mb_strlen($word, 'UTF-8');
        return $n >= 9 ? 2 : ($n >= 5 ? 1 : 0);
    }

    // ---------- lexical matching ----------

    /**
     * Index words within the typo budget of `word`, for words the index does
     * not contain as they are. Each comes with its distance.
     *
     * @return array<int, array{word: string, distance: int}>
     */
    public function typo_neighbours(string $word): array {
        if (isset($this->frequency[$word])) {
            return [];
        }
        $budget = self::typo_budget($word);
        if ($budget === 0) {
            return [];
        }
        $n = mb_strlen($word, 'UTF-8');
        $found = [];
        for ($len = $n - $budget; $len <= $n + $budget; $len++) {
            foreach ($this->by_length[$len] ?? [] as $candidate) {
                if (self::typo_budget($candidate) === 0) {
                    continue;
                }
                $d = self::edit_distance($word, $candidate, $budget);
                if ($d <= $budget) {
                    $found[] = ['word' => $candidate, 'distance' => $d];
                }
            }
        }
        return $found;
    }

    /**
     * The query's words with typos corrected towards the index: each query
     * word maps to itself and to any index word within its typo budget.
     *
     * @return array<string, array<int, array{word: string, distance: int}>>
     */
    public function expand_query(string $query): array {
        $expanded = [];
        foreach (self::words($query) as $word) {
            if (!isset($expanded[$word])) {
                $expanded[$word] = [['word' => $word, 'distance' => 0], ...$this->typo_neighbours($word)];
            }
        }
        return $expanded;
    }

    /**
     * Lexical evidence for one term: a score in [0, 1) and what matched.
     *
     * @return array{score: float, matched: array[]}
     */
    public function lexical_match(array $term, string $query, ?array $expanded = null, ?array $query_words = null, ?array $content = null): array {
        $expanded ??= $this->expand_query($query);
        $query_words ??= self::words($query);
        $content ??= self::content_words($query);
        $matched = [];
        $raw = 0.0;

        $covered_by_phrase = [];
        foreach ($term['phrases'] as $phrase) {
            // A phrase matches exactly, or with each of its words replaced by
            // a typo'd query word ("projektleeder" for "projektleder").
            $typos = 0;
            $hit = self::contains_phrase($query_words, $phrase['words']);
            if (!$hit) {
                $corrected = [];
                foreach ($query_words as $w) {
                    $near = null;
                    foreach ($expanded[$w] ?? [] as $candidate) {
                        if ($candidate['distance'] > 0 && in_array($candidate['word'], $phrase['words'], true)) {
                            $near = $candidate;
                            break;
                        }
                    }
                    if ($near) {
                        $typos++;
                    }
                    $corrected[] = $near ? $near['word'] : $w;
                }
                $hit = $typos > 0 && self::contains_phrase($corrected, $phrase['words']);
            }
            if (!$hit) {
                continue;
            }
            // Longer phrases are more specific: "projektleder i byggeriet"
            // says more than "projektleder". A one-word phrase is only as
            // specific as the word is rare: "sap" names one term, "ledelse"
            // hundreds. A label that is the whole query ("Kørekort B" for
            // "kørekort B", or "SAP" for "erfaring med SAP") beats one merely
            // inside it.
            $whole = $phrase['words'] === $query_words || $phrase['words'] === $content;
            $count = count($phrase['words']);
            $specificity = ($count > 1
                ? 1 + 0.5 * ($count - 1)
                : 0.4 + 0.6 * $this->rarity($phrase['words'][0])) * ($whole ? 2 : 1);
            $raw += self::WEIGHTS[$phrase['field']] * $specificity * ($typos ? self::WEIGHTS['typo'] : 1);
            $matched[] = ['text' => $phrase['text'], 'field' => $phrase['field'], 'lang' => $phrase['lang'], 'typos' => $typos];
            foreach ($phrase['words'] as $w) {
                $covered_by_phrase[$w] = true;
            }
        }

        foreach ($content as $query_word) {
            foreach ($expanded[$query_word] ?? [] as ['word' => $word, 'distance' => $distance]) {
                if (isset($covered_by_phrase[$word])) {
                    break;
                }
                $r = $this->rarity($word) * ($distance ? self::WEIGHTS['typo'] : 1);
                if (isset($term['keywords'][$word])) {
                    $raw += self::WEIGHTS['keyword'] * $r;
                    $matched[] = ['text' => $word, 'field' => 'keyword', 'typos' => $distance];
                    break;
                }
                if (isset($term['text'][$word])) {
                    $raw += self::WEIGHTS['text'] * $r;
                    $matched[] = ['text' => $word, 'field' => 'text', 'typos' => $distance];
                    break;
                }
            }
        }

        return ['score' => 1 - exp(-$raw), 'matched' => $matched];
    }

    /**
     * Rarity of a word across terms, from 0 (in every term) towards 1 (in
     * one). A word every term shares cannot tell terms apart.
     */
    private function rarity(string $word): float {
        $n = count($this->terms);
        $df = $this->frequency[$word] ?? 0;
        if ($df === 0) {
            return 0.0;
        }
        return $n < 2 ? 1.0 : log($n / $df) / log($n);
    }

    /** Cosine of `query_vector` with term row `row` of the int8 index matrix. */
    private function term_cosine(int $row, array $query_vector): float {
        $weights = unpack("c{$this->dim}", $this->weights, $row * $this->dim);
        $dot = 0.0;
        foreach ($query_vector as $i => $v) {
            $dot += $v * $weights[$i + 1];
        }
        return $dot * $this->scales[$row];
    }

    private static function contains_phrase(array $haystack, array $phrase): bool {
        $n = count($phrase);
        for ($i = 0; $i + $n <= count($haystack); $i++) {
            if (array_slice($haystack, $i, $n) === $phrase) {
                return true;
            }
        }
        return false;
    }

    private static function is_stopword(string $word): bool {
        self::$stopwords ??= array_fill_keys(explode(' ', self::STOPWORDS), true);
        return isset(self::$stopwords[$word]);
    }

    private static function qualifiers(): array {
        return self::$qualifiers ??= array_fill_keys(explode(' ', self::QUALIFIERS), true);
    }

    private static function read(string $path): string {
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException("cannot read $path");
        }
        return $data;
    }

}
