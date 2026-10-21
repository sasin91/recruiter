<?php
require_once __DIR__ . '/../job_posts/Job_post_rules.php';

/**
 * A job post as a row of the company dashboard's table (views/home.php).
 */
class Company_post_row {

    /** ['id', 'title', 'dated', 'status', 'status_name', 'applications', 'new', 'shortlisted', 'review_label', 'has_candidates']. */
    public static function from(array $p): array {
        return [
            'id' => (int) $p['id'],
            'title' => (string) $p['title'],
            'dated' => self::dated($p),
            'status' => (string) $p['status'],
            'status_name' => Job_post_rules::STATUS_NAMES[$p['status']] ?? (string) $p['status'],
            'applications' => (int) $p['applications'],
            'new' => (int) $p['new'],
            'shortlisted' => (int) $p['shortlisted'],
            'review_label' => $p['status'] === 'draft' ? 'Review' : 'Edit',
            'has_candidates' => $p['status'] !== 'draft',
        ];
    }

    /** "Published 21 Oct 2026", or "Created …" for a post never published. */
    private static function dated(array $p): string {
        return $p['published_at'] ? 'Published ' . date('j M Y', (int) $p['published_at']) : 'Created ' . date('j M Y', (int) $p['created_at']);
    }
}
