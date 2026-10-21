<?php
require_once __DIR__ . '/Job_post_rules.php';

/**
 * The head and the buttons of a job post's review page (views/review.php),
 * for the company's staff.
 */
class Job_post_review {

    /** ['status_name', 'read_by', 'is_draft', 'buttons' => [['url', 'label', 'fields']]]. */
    public static function from(array $post): array {
        return [
            'status_name' => Job_post_rules::STATUS_NAMES[$post['status']] ?? (string) $post['status'],
            'read_by' => self::read_by((string) $post['extractor']),
            'is_draft' => $post['status'] === 'draft',
            'buttons' => self::buttons($post),
        ];
    }

    /** "the free reader", or "AI (gpt-5.4-mini)". */
    private static function read_by(string $extractor): string {
        return $extractor === 'free_reader' ? 'the free reader' : "AI ($extractor)";
    }

    /** The post's status actions (publishing is the form's own button), Duplicate, and Delete for a draft. */
    private static function buttons(array $post): array {
        $buttons = array_map(
            fn(string $action) => self::button($post, 'submit_status', Job_post_rules::ACTION_NAMES[$action], ['action' => $action]),
            array_values(array_diff(Job_post_rules::actions($post['status']), ['publish']))
        );
        $buttons[] = self::button($post, 'submit_duplicate', 'Duplicate');
        if ($post['status'] === 'draft') {
            $buttons[] = self::button($post, 'submit_delete', 'Delete draft');
        }
        return $buttons;
    }

    /** A one-button POST form to job_posts/$method/{id}. */
    private static function button(array $post, string $method, string $label, array $fields = []): array {
        return ['url' => "job_posts/$method/" . (int) $post['id'], 'label' => $label, 'fields' => $fields];
    }
}
