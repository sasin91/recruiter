<?php
/**
 * One requirement of a match with its verdict, as the result prints it
 * (views/result.php): the verdict, the requirement, why, the evidence
 * quoted from the CV, what decided it and, for a requirement met by any of
 * several, each alternative as a row of its own.
 *
 * from() takes a fresh match's item (Cv_matcher::criteria) and the verdicts
 * by item id (Cv_matcher::combine); from_saved() a saved match's item
 * (cv_match_items).
 */
class Verdict_row {

    // Missing first, then partial, then met.
    public const ORDER = ['missing' => 0, 'partial' => 1, 'met' => 2];

    private const NO_VERDICT = ['verdict' => 'missing', 'by' => 'llm', 'method' => '', 'reason' => 'No verdict returned.', 'evidence' => ''];

    /**
     * ['verdict' => 'met', 'text' => 'PHP', 'reason' => ..., 'evidence' => ...,
     * 'decided_by' => 'taxonomy, exact', 'alternatives' => [rows]].
     */
    public static function from(array $item, array $verdicts): array {
        $verdict = ($verdicts[$item['id']] ?? null) ?: self::NO_VERDICT;
        return self::row((string) $item['text'], $verdict, self::decided_by($verdict), array_map(
            fn(array $alternative) => self::from($alternative, $verdicts),
            $item['alternatives'] ?? []
        ));
    }

    public static function from_saved(array $item): array {
        return self::row((string) $item['text'], $item, self::saved_decided_by((string) $item['decided_by']), []);
    }

    /** The rows sorted missing first; rows with the same verdict keep their order. */
    public static function sorted(array $rows): array {
        usort($rows, fn(array $a, array $b) => self::ORDER[$a['verdict']] <=> self::ORDER[$b['verdict']]);
        return $rows;
    }

    private static function row(string $text, array $verdict, string $decided_by, array $alternatives): array {
        return [
            'verdict' => self::verdict($verdict),
            'text' => $text,
            'reason' => (string) ($verdict['reason'] ?? ''),
            'evidence' => (string) ($verdict['evidence'] ?? ''),
            'decided_by' => $decided_by,
            'alternatives' => $alternatives,
        ];
    }

    private static function verdict(array $verdict): string {
        return isset(self::ORDER[$verdict['verdict'] ?? '']) ? $verdict['verdict'] : 'missing';
    }

    /** "model", "rule", "keyword match" or "taxonomy, exact". */
    private static function decided_by(array $verdict): string {
        return match (true) {
            ($verdict['by'] ?? '') === 'llm' => 'model',
            ($verdict['by'] ?? '') === 'rule' => 'rule',
            ($verdict['method'] ?? '') === 'not_found' => 'keyword match',
            default => 'taxonomy, ' . ($verdict['method'] ?? ''),
        };
    }

    /** The same words for a saved item, where decided_by is the method ("llm" and "years" aside). */
    private static function saved_decided_by(string $decided_by): string {
        return match ($decided_by) {
            'llm' => 'model',
            'years' => 'rule',
            'not_found' => 'keyword match',
            default => "taxonomy, $decided_by",
        };
    }
}
