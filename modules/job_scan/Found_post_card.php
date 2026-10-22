<?php
require_once __DIR__ . '/../cv_match/Match_score.php';

/**
 * A post a job search found (scanned_job_posts) as a card of the search's
 * list (views/posts.php): what it is, how it matched, and its buttons.
 */
class Found_post_card {

    public const STATUS_NAMES = [
        'new' => 'Waiting to be matched',
        'matching' => 'Matching',
        'matched' => 'Matched',
        'not_a_job' => 'Not a job post',
        'failed' => 'Failed',
        'dismissed' => 'Dismissed',
    ];

    /** The card's fields, ready to print. */
    public static function from(array $p): array {
        return [
            'id' => (int) $p['id'],
            'url' => (string) $p['url'],
            'heading' => self::heading($p),
            'meta' => self::meta_line($p),
            'score' => self::shown_score($p),
            'status' => (string) $p['status'],
            'status_name' => self::STATUS_NAMES[$p['status']] ?? (string) $p['status'],
            'error' => self::shown_error($p),
            'disqualifier' => self::disqualifier($p),
            'match_link' => self::match_link($p),
            'can_retry' => in_array($p['status'], ['failed', 'not_a_job'], true),
            'dismiss' => self::dismiss_button($p),
        ];
    }

    /** The post's title, or its link when the scan found no title. */
    private static function heading(array $p): string {
        return $p['title'] !== '' ? (string) $p['title'] : (string) $p['url'];
    }

    /** "Nordisk Fragt · Odense · found 21 Oct 08:12". */
    private static function meta_line(array $p): string {
        $parts = [(string) $p['company'], (string) $p['location'], 'found ' . date('j M H:i', (int) $p['found_at'])];
        return implode(' · ', array_filter($parts, fn($part) => $part !== ''));
    }

    /** The score (Match_score::from) once the post is matched, else null. */
    private static function shown_score(array $p): ?array {
        return $p['status'] === 'matched' ? Match_score::from($p['score'], $p['tag']) : null;
    }

    /** "Likely disqualifier: Located close to Copenhagen" for a missed location, else ''. */
    private static function disqualifier(array $p): string {
        return ($p['location_missed'] ?? null) !== null ? 'Likely disqualifier: ' . $p['location_missed'] : '';
    }

    /** Why matching failed; a dismissed post keeps quiet about it. */
    private static function shown_error(array $p): string {
        return $p['status'] === 'dismissed' ? '' : (string) $p['error'];
    }

    /** ['url', 'label'] of the saved match, or null before there is one. */
    private static function match_link(array $p): ?array {
        if ($p['cv_match_id'] === null) {
            return null;
        }
        return [
            'url' => 'cv_match/' . (int) $p['cv_match_id'],
            'label' => self::match_label($p),
        ];
    }

    /** The match link's text: what is ready to open, the tailored résumé and the application. */
    private static function match_label(array $p): string {
        return match (true) {
            $p['resume_written_at'] && $p['application_written_at'] => 'Open the match, tailored résumé and application',
            (bool) $p['resume_written_at'] => 'Open the match and tailored résumé',
            (bool) $p['application_written_at'] => 'Open the match and application',
            default => 'Open the match',
        };
    }

    /** ['label', 'fields']: Dismiss, or Bring back for a dismissed post. */
    private static function dismiss_button(array $p): array {
        return $p['status'] === 'dismissed'
            ? ['label' => 'Bring back', 'fields' => ['undo' => '1']]
            : ['label' => 'Dismiss', 'fields' => []];
    }
}
