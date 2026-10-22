<?php
require_once __DIR__ . '/Cv_matcher.php';

/**
 * Where the post asks the candidate to live or show up, when they likely
 * don't (Cv_matcher::missed_locations), as the result prints it above the
 * requirements (views/location_notice.php): the post's words and the
 * model's reason. Such a match is capped just under "medium"
 * (Cv_matcher::LOCATION_MISSED_INDEX), not hidden.
 */
class Location_notice {

    public const HEADING = 'Likely disqualifier: where you live';
    public const NOTE = 'The score is capped below medium for this. Apply anyway if you can move or commute.';

    /** A match just made: its groups (Cv_matcher::criteria) and combined verdicts. */
    public static function from(array $groups, array|object $verdicts): ?array {
        $verdicts = (array) $verdicts;
        return self::notice(array_map(
            fn(array $item) => ['text' => (string) $item['text'], 'reason' => (string) ($verdicts[$item['id']]['reason'] ?? '')],
            Cv_matcher::missed_locations($groups, $verdicts)
        ));
    }

    /** A saved match's items (cv_match_items). */
    public static function from_saved(array $items): ?array {
        $missed = array_filter($items, fn(array $item) => $item['criterion'] === 'location' && $item['verdict'] === 'missing');
        return self::notice(array_map(
            fn(array $item) => ['text' => (string) $item['text'], 'reason' => (string) $item['reason']],
            array_values($missed)
        ));
    }

    /** ['heading', 'note', 'lines' => [['text', 'reason']]], or null when nothing is missed. */
    private static function notice(array $lines): ?array {
        return $lines ? ['heading' => self::HEADING, 'note' => self::NOTE, 'lines' => $lines] : null;
    }
}
