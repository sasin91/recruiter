<?php
/**
 * Why a match was rejected (cv_match_rejections): one or more of a fixed
 * list, so reasons that keep coming back can be counted, each with the
 * person's own note; 'other' needs one.
 */
class Reject_reason {

    public const REASONS = [
        'commute' => 'Too long commute',
        'salary' => 'Salary',
        'tech_stack' => 'Tech stack',
        'seniority' => 'Seniority level',
        'language' => 'Language',
        'company' => 'The company',
        'job_type' => 'Hours or contract',
        'other' => 'Other',
    ];
    public const NOTE_MAX = 255;

    /**
     * What's wrong with the reasons ticked and the notes by reason as
     * posted, for the person to read, or '' when they can be saved.
     */
    public static function problem(mixed $reasons, mixed $notes): string {
        if (!self::known($reasons)) {
            return 'Tick why you are not interested.';
        }
        $rows = self::rows($reasons, $notes);
        if (in_array(['reason' => 'other', 'note' => ''], $rows, true)) {
            return 'Say in a few words what the other reason is.';
        }
        foreach ($rows as $row) {
            if (mb_strlen($row['note']) > self::NOTE_MAX) {
                return 'Keep each note under ' . self::NOTE_MAX . ' characters.';
            }
        }
        return '';
    }

    /**
     * The rejections to save: [['reason' => 'commute', 'note' => '1h each
     * way'], ...], one per reason ticked with its note (trimmed, '' when
     * none). $reasons as problem() accepts them.
     */
    public static function rows(array $reasons, mixed $notes): array {
        $notes = is_array($notes) ? $notes : [];
        return array_map(fn($reason) => [
            'reason' => $reason,
            'note' => is_string($notes[$reason] ?? null) ? trim($notes[$reason]) : '',
        ], array_values(array_unique($reasons)));
    }

    /**
     * "Too long commute (1h each way), Salary, Recruiter agency": each
     * rejection by its label and note, Other by its note alone; '' for none.
     */
    public static function describe(array $rejections): string {
        return implode(', ', array_map([self::class, 'one'], $rejections));
    }

    private static function one(array $rejection): string {
        $label = self::REASONS[$rejection['reason']] ?? (string) $rejection['reason'];
        $note = trim((string) $rejection['note']);
        if ($note === '') {
            return $label;
        }
        return $rejection['reason'] === 'other' ? $note : "$label ($note)";
    }

    /** Whether $reasons is a non-empty list of REASONS keys. */
    private static function known(mixed $reasons): bool {
        if (!is_array($reasons) || $reasons === [] || !array_is_list($reasons)) {
            return false;
        }
        foreach ($reasons as $reason) {
            if (!is_string($reason) || !isset(self::REASONS[$reason])) {
                return false;
            }
        }
        return true;
    }
}
