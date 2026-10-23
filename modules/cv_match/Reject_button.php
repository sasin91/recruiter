<?php
require_once __DIR__ . '/Reject_reason.php';

/**
 * The Reject part of a saved match (views/result.php): "Not interested"
 * opens a form asking why (Reject_reason, as many as apply) with room for a note; a
 * rejected match says why it was rejected and offers Bring back. Plain
 * form posts to cv_match/submit_reject/{id}.
 */
class Reject_button {

    public const OPEN = 'Not interested';
    public const REJECT = 'Reject';
    public const NOTE_PLACEHOLDER = 'Note (optional)';
    public const OTHER_PLACEHOLDER = 'What is it? (needed for Other)';
    public const BRING_BACK = 'Bring back';
    public const REJECTED = 'You rejected this match';
    public const REJECTED_WHERE = 'It shows only in the Rejected list.';

    /**
     * For a match not rejected: ['action', 'rejected' => false, 'open',
     * 'reasons' => [['value', 'label', 'placeholder'], ...], 'note_max',
     * 'submit']: a checkbox and a note per reason.
     * For a rejected one: ['action', 'rejected' => true, 'note' => "You
     * rejected this match (Too long commute (1h each way), Salary). It shows
     * only in the Rejected list.", 'fields' => ['undo' => '1'], 'submit' =>
     * "Bring back"]. Null for a match that isn't saved. $match: rejected_at
     * and rejections (Cv_match_model::find).
     */
    public static function from(?int $saved_id, array $match = []): ?array {
        if ($saved_id === null) {
            return null;
        }
        $action = "cv_match/submit_reject/$saved_id";
        return ($match['rejected_at'] ?? null) === null ? self::reject_form($action) : self::bring_back($action, $match);
    }

    private static function reject_form(string $action): array {
        return [
            'action' => $action,
            'rejected' => false,
            'open' => self::OPEN,
            'reasons' => self::reasons(),
            'note_max' => Reject_reason::NOTE_MAX,
            'submit' => self::REJECT,
        ];
    }

    private static function bring_back(string $action, array $match): array {
        $why = Reject_reason::describe($match['rejections'] ?? []);
        return [
            'action' => $action,
            'rejected' => true,
            'note' => self::REJECTED . ($why !== '' ? " ($why)" : '') . '. ' . self::REJECTED_WHERE,
            'fields' => ['undo' => '1'],
            'submit' => self::BRING_BACK,
        ];
    }

    /** Each reason with its label and its note's placeholder. */
    private static function reasons(): array {
        $reasons = [];
        foreach (Reject_reason::REASONS as $value => $label) {
            $reasons[] = ['value' => $value, 'label' => $label, 'placeholder' => $value === 'other' ? self::OTHER_PLACEHOLDER : self::NOTE_PLACEHOLDER];
        }
        return $reasons;
    }
}
