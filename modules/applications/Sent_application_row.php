<?php
/**
 * One of a candidate's applications as a row of "My applications"
 * (views/mine.php).
 */
class Sent_application_row {

    private const STATUS_NAMES = ['in_review' => 'Sent', 'rejected' => 'Not this time', 'hired' => 'Hired', 'withdrawn' => 'Withdrawn'];

    /** ['id', 'title', 'company_name', 'post_url', 'status_name', 'status_class', 'sent', 'can_withdraw', 'apply_again_url']. */
    public static function from(array $a): array {
        return [
            'id' => (int) $a['id'],
            'title' => (string) $a['title'],
            'company_name' => (string) $a['company_name'],
            'post_url' => 'jobs/' . $a['public_token'],
            'status_name' => self::STATUS_NAMES[$a['status']] ?? (string) $a['status'],
            'status_class' => $a['status'] === 'in_review' ? 'active' : 'closed',
            'sent' => date('j M Y', (int) $a['submitted_at']),
            'can_withdraw' => $a['status'] === 'in_review',
            'apply_again_url' => self::apply_again_url($a),
        ];
    }

    /** A withdrawn application to a post that is still live can be sent again. */
    private static function apply_again_url(array $a): ?string {
        return $a['status'] === 'withdrawn' && $a['post_status'] === 'active' ? 'jobs/' . $a['public_token'] . '/apply' : null;
    }
}
