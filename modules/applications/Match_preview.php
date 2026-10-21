<?php
require_once __DIR__ . '/../cv_match/Match_score.php';

/**
 * How a candidate's saved CV matches a post before they send it
 * (views/apply.php): the score and, per list, each requirement's verdict
 * in the candidate's words.
 */
class Match_preview {

    private const VERDICT_NAMES = ['met' => 'In your CV', 'partial' => 'Something close', 'missing' => 'Not found'];

    private const LIST_HEADINGS = ['requirements' => 'What they ask for', 'skills' => 'Nice to have'];

    /**
     * $groups as Cv_matcher::criteria, $verdicts combined (Cv_matcher::combine),
     * $result as Cv_matcher::score. ['score', 'lists' => [['heading', 'items' => [['text', 'verdict', 'verdict_name']]]]].
     */
    public static function from(array $groups, array $verdicts, array $result): array {
        return [
            'score' => Match_score::from($result['index'], $result['tag']),
            'lists' => self::lists($groups, $verdicts),
        ];
    }

    /** The lists that have requirements, in LIST_HEADINGS order. */
    private static function lists(array $groups, array $verdicts): array {
        $lists = [];
        foreach (self::LIST_HEADINGS as $group => $heading) {
            if ($groups[$group]) {
                $lists[] = ['heading' => $heading, 'items' => array_map(fn(array $item) => self::item($item, $verdicts), $groups[$group])];
            }
        }
        return $lists;
    }

    private static function item(array $item, array $verdicts): array {
        $verdict = $verdicts[$item['id']]['verdict'] ?? 'missing';
        return ['text' => (string) $item['text'], 'verdict' => $verdict, 'verdict_name' => self::VERDICT_NAMES[$verdict]];
    }
}
