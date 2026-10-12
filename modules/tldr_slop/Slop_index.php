<?php
require_once __DIR__ . '/../../taxonomy/php/Static_model.php';
require_once __DIR__ . '/Slop_reference.php';
/**
 * Embeds an article's sentences with the taxonomy's static model
 * (potion-multilingual, taxonomy/php/Static_model.php) and indexes them
 * against Slop_reference's sentences embedded with the same model, which
 * gives what /tldr-slop shows and what Laya is asked about:
 *
 * - filler: a sentence whose three nearest slop references are, on
 *   average, at least FILLER_MARGIN more similar than its three nearest
 *   substance references. Checked on 20 held-out sentences (10 each way):
 *   filler sat at +0.09 to +0.23, substance at -0.17 to +0.06.
 * - repeats: a sentence at cosine REPEAT or more to an earlier one that
 *   isn't its neighbour, i.e. the same point made again.
 * - figures: sentences with a digit in them (numbers, versions, sizes).
 * - stock words: the vocabulary of generated text (STOCK_WORDS), counted
 *   as words, since a static model can't tell "delve" from "dig".
 * - TL;DR: the TLDR_SENTENCES sentences nearest the centre of the
 *   article's non-filler sentences, in the article's order.
 *
 * Laya then judges the counts, not the text: the bake-off found the English
 * checkpoint reliable on a code-computed summary and weak on raw text
 * (eval/README.md), so laya_summary() puts the counts in one English
 * paragraph and questions() asks a yes/no and a 0-4 score about it.
 */
class Slop_index {

    const FILLER_MARGIN = 0.08;
    const NEAREST = 3;
    const REPEAT = 0.92;
    const TLDR_SENTENCES = 3;
    const TLDR_MIN_WORDS = 8;

    const STOCK_WORDS = ['delve', 'delves', 'delving', 'tapestry', 'testament', 'game-changer', 'game-changing',
        'unlock', 'unlocks', 'unleash', 'seamless', 'seamlessly', 'leverage', 'leveraging', 'landscape', 'realm',
        'embark', 'elevate', 'pivotal', 'crucial', 'robust', 'foster', 'fostering', 'empower', 'empowers',
        'transformative', 'revolutionize', 'revolutionizes', 'cutting-edge', 'ever-evolving', 'synergy',
        'holistic', 'paradigm', 'navigate', 'navigating', 'intricacies', 'underscores', 'showcasing', 'vibrant'];

    const SUBSTANCE_LEVELS = ['no real content', 'very little', 'some', 'a lot', 'dense with specifics'];

    /** @var array<int, float[]> */
    private array $slop = [];
    /** @var array<int, float[]> */
    private array $substance = [];

    public function __construct(private readonly Static_model $model) {
        foreach (Slop_reference::SLOP as $sentence) {
            $this->slop[] = $model->encode($sentence)['vector'];
        }
        foreach (Slop_reference::SUBSTANCE as $sentence) {
            $this->substance[] = $model->encode($sentence)['vector'];
        }
    }

    /** The index on the shipped model (taxonomy/models). */
    public static function load(): self {
        return new self(Static_model::load(dirname(__DIR__, 2) . '/taxonomy/models/potion-multilingual-da'));
    }

    /**
     * How far $vector leans to the slop side: the mean cosine of its
     * NEAREST slop references minus that of its NEAREST substance ones.
     */
    public function lean(array $vector): float {
        return self::nearest($vector, $this->slop) - self::nearest($vector, $this->substance);
    }

    /**
     * The article (Article_reader) indexed: per sentence {text, lean, filler,
     * repeats, figure}, the counts, the stock words found and the TL;DR.
     */
    public function assess(array $article): array {
        $sentences = [];
        $vectors = [];
        foreach ($article['sentences'] as $i => $text) {
            $vector = $this->model->encode($text)['vector'];
            $lean = $vector === null ? 0.0 : $this->lean($vector);
            $repeats = null;
            if ($vector !== null) {
                for ($j = 0; $j < $i - 1; $j++) {
                    if (isset($vectors[$j]) && Static_model::cosine($vectors[$j], $vector) >= self::REPEAT) {
                        $repeats = $j;
                        break;
                    }
                }
                $vectors[$i] = $vector;
            }
            $sentences[] = [
                'text' => $text,
                'lean' => round($lean, 3),
                'filler' => $lean >= self::FILLER_MARGIN,
                'repeats' => $repeats,
                'figure' => (bool) preg_match('/\d/', $text),
            ];
        }

        $stock = self::stock_words(implode(' ', $article['sentences']));
        $count = fn(string $key) => count(array_filter($sentences, fn($s) => $s[$key] !== false && $s[$key] !== null));
        return [
            'sentences' => $sentences,
            'counts' => [
                'sentences' => count($sentences),
                'words' => $article['words'],
                'filler' => $count('filler'),
                'repeats' => $count('repeats'),
                'figures' => $count('figure'),
                'code_blocks' => $article['code_blocks'],
                'links' => $article['links'],
                'stock_words' => array_sum($stock),
            ],
            'stock_words' => $stock,
            'tldr' => $this->tldr($sentences, $vectors),
        ];
    }

    /**
     * The TL;DR: the sentences nearest the centre of the non-filler
     * sentences (all of them when every one is filler), in article order.
     *
     * @return string[]
     */
    public function tldr(array $sentences, array $vectors): array {
        $pool = array_filter(array_keys($vectors), fn($i) => !$sentences[$i]['filler']
            && preg_match_all('/[\p{L}\p{N}]+/u', $sentences[$i]['text']) >= self::TLDR_MIN_WORDS);
        if (!$pool) {
            $pool = array_keys($vectors);
        }
        if (!$pool) {
            return [];
        }
        $centre = array_fill(0, count($vectors[$pool[array_key_first($pool)]]), 0.0);
        foreach ($pool as $i) {
            foreach ($vectors[$i] as $d => $v) {
                $centre[$d] += $v;
            }
        }
        $centre = Static_model::unit($centre);
        $scores = [];
        foreach ($pool as $i) {
            if ($sentences[$i]['repeats'] === null) {
                $scores[$i] = Static_model::cosine($centre, $vectors[$i]);
            }
        }
        arsort($scores);
        $picked = array_slice(array_keys($scores), 0, self::TLDR_SENTENCES);
        sort($picked);
        return array_map(fn($i) => $sentences[$i]['text'], $picked);
    }

    /** Each stock word found in $text with how often, most first. */
    public static function stock_words(string $text): array {
        $found = [];
        preg_match_all('/[\p{L}]+(?:-[\p{L}]+)*/u', mb_strtolower($text), $words);
        foreach ($words[0] as $word) {
            if (in_array($word, self::STOCK_WORDS, true)) {
                $found[$word] = ($found[$word] ?? 0) + 1;
            }
        }
        arsort($found);
        return $found;
    }

    /**
     * The state Laya judges: the counts in one English paragraph, worded
     * like Cv_matcher::laya_summary() (the title, then plain counts).
     */
    public static function laya_summary(string $title, array $counts, array $stock_words): string {
        $n = max(1, $counts['sentences']);
        $share = fn(int $k) => (int) round($k / $n * 100);
        $words = $stock_words
            ? implode(', ', array_map(fn($w, $k) => $k > 1 ? "$w ($k)" : $w, array_keys($stock_words), $stock_words))
            : 'none';
        return 'Article: ' . ($title !== '' ? $title : 'untitled') . '. '
            . "{$counts['words']} words in {$counts['sentences']} sentences. "
            . "{$counts['filler']} of {$counts['sentences']} sentences ({$share($counts['filler'])}%) read like generic filler. "
            . self::plural($counts['repeats'], 'sentence repeats', 'sentences repeat') . ' an earlier point. '
            . self::plural($counts['figures'], 'sentence', 'sentences') . " ({$share($counts['figures'])}%) "
            . ($counts['figures'] === 1 ? 'gives' : 'give') . ' a figure. '
            . 'It has ' . self::plural($counts['code_blocks'], 'code block', 'code blocks') . ' and '
            . self::plural($counts['links'], 'link', 'links') . ' to other sites. '
            . "Stock AI words: $words.";
    }

    /** The questions Laya answers about laya_summary(). */
    public static function questions(): array {
        return [
            'slop' => ['type' => 'noul', 'instructions' => 'Is this article AI slop: generic, padded text with little real content?'],
            'substance' => ['type' => 'score', 'instructions' => 'How much real, specific content does the article have?',
                'criteria' => self::SUBSTANCE_LEVELS],
        ];
    }

    /**
     * Laya's answers as `slop` (probability), `substance` (0-4),
     * `substance_label` and `verdict` ('slop' at 0.5 or more, else 'not slop').
     */
    public static function read_answer(array $answers): array {
        $slop = $answers['slop']['noul'] ?? null;
        $substance = $answers['substance']['score'] ?? null;
        if (!is_numeric($slop) || !is_numeric($substance)) {
            throw new RuntimeException('Laya answered without the slop and substance scores.');
        }
        $substance = max(0.0, min(4.0, (float) $substance));
        return [
            'slop' => round((float) $slop, 4),
            'substance' => round($substance, 3),
            'substance_label' => self::SUBSTANCE_LEVELS[(int) round($substance)],
            'verdict' => $slop >= 0.5 ? 'slop' : 'not slop',
        ];
    }

    private static function plural(int $n, string $one, string $more): string {
        return $n === 1 ? "1 $one" : "$n $more";
    }

    /** Mean cosine of $vector's NEAREST neighbours in $set. */
    private static function nearest(array $vector, array $set): float {
        $cosines = [];
        foreach ($set as $reference) {
            $cosines[] = Static_model::cosine($reference, $vector);
        }
        rsort($cosines);
        $top = array_slice($cosines, 0, self::NEAREST);
        return array_sum($top) / count($top);
    }
}
