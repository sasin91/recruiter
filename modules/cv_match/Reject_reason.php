<?php
/**
 * Why a match was rejected (cv_match_reject_reasons): one or more of a
 * fixed list, so reasons that keep coming back can be counted, plus the
 * person's own words (cv_matches.reject_note), which 'other' needs.
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
     * What's wrong with the reasons and note as posted, for the person to
     * read, or '' when they can be saved.
     */
    public static function problem(mixed $reasons, string $note): string {
        if (!self::known($reasons)) {
            return 'Tick why you are not interested.';
        }
        if (in_array('other', $reasons, true) && trim($note) === '') {
            return 'Say in a few words why you are not interested.';
        }
        return mb_strlen($note) > self::NOTE_MAX ? 'Keep the note under ' . self::NOTE_MAX . ' characters.' : '';
    }

    /** The reasons as listed by the database ("commute,salary"), or none. */
    public static function listed(?string $reasons): array {
        return $reasons === null || $reasons === '' ? [] : explode(',', $reasons);
    }

    /**
     * "Too long commute, Salary (1h each way)", the note alone when Other
     * is the only reason, or '' for none.
     */
    public static function describe(array $reasons, string $note): string {
        $labels = implode(', ', array_map(fn($r) => self::REASONS[$r] ?? $r, array_diff($reasons, ['other'])));
        $note = trim($note);
        if ($labels === '' || $note === '') {
            return $labels !== '' ? $labels : $note;
        }
        return "$labels ($note)";
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
