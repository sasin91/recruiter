<?php
/**
 * Why a match was rejected (cv_matches.reject_reason): a fixed list, so a
 * reason that keeps coming back can be counted, plus the person's own words
 * (reject_note), which 'other' needs.
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
     * What's wrong with a reason and note as posted, for the person to
     * read, or '' when they can be saved.
     */
    public static function problem(mixed $reason, string $note): string {
        if (!is_string($reason) || !isset(self::REASONS[$reason])) {
            return 'Pick why you are not interested.';
        }
        if ($reason === 'other' && trim($note) === '') {
            return 'Say in a few words why you are not interested.';
        }
        return mb_strlen($note) > self::NOTE_MAX ? 'Keep the note under ' . self::NOTE_MAX . ' characters.' : '';
    }

    /**
     * "Too long commute (1h each way)", "Too long commute", the note alone
     * for Other, or '' for no reason.
     */
    public static function describe(?string $reason, string $note): string {
        $label = self::REASONS[$reason ?? ''] ?? '';
        $note = trim($note);
        if ($note === '' || $label === '') {
            return $label !== '' ? $label : $note;
        }
        return $reason === 'other' ? $note : "$label ($note)";
    }
}
