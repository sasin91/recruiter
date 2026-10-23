<?php
/**
 * The Reject button of a saved match (views/result.php): Reject when the
 * person isn't interested, so the match leaves every list but Rejected, or
 * Bring back for a rejected one. A plain form post to
 * cv_match/submit_reject/{id}.
 */
class Reject_button {

    public const REJECT = 'Not interested';
    public const BRING_BACK = 'Bring back';
    public const REJECTED_NOTE = 'You rejected this match: it shows only in the Rejected list.';

    /**
     * ['action' => "cv_match/submit_reject/9", 'label' => "Not interested",
     * 'fields' => hidden fields, 'note' => '' or REJECTED_NOTE], or null for
     * a match that isn't saved. $rejected_at: cv_matches.rejected_at.
     */
    public static function from(?int $saved_id, int|string|null $rejected_at): ?array {
        if ($saved_id === null) {
            return null;
        }
        $rejected = $rejected_at !== null;
        return [
            'action' => "cv_match/submit_reject/$saved_id",
            'label' => $rejected ? self::BRING_BACK : self::REJECT,
            'fields' => $rejected ? ['undo' => '1'] : [],
            'note' => $rejected ? self::REJECTED_NOTE : '',
        ];
    }
}
