<?php
require_once __DIR__ . '/Match_score.php';

/**
 * A saved match (Cv_match_model::recent) as a row of the CV checker's list
 * (views/history.php): its id, tag, title and meta line, ready to print.
 */
class Saved_match_row {

    /** ['id' => 7, 'tag' => 'good', 'title' => ..., 'meta' => ...]. */
    public static function from(array $m): array {
        return [
            'id' => (int) $m['id'],
            'tag' => (string) $m['tag'],
            'title' => self::title($m),
            'meta' => self::meta_line($m),
        ];
    }

    /** "Job title · Company", or "Untitled" when the post named neither. */
    private static function title(array $m): string {
        $title = implode(' · ', array_filter([(string) $m['job_title'], (string) $m['company']]));
        return $title !== '' ? $title : 'Untitled';
    }

    /** "81% good · tailored résumé · 21 Oct 2026 · application · on Jobnet". */
    private static function meta_line(array $m): string {
        $parts = [Match_score::short_label($m['score'], (string) $m['tag']), ...self::tailored_label($m), date('j M Y', (int) $m['created_at']), ...self::written_labels($m)];
        return implode(' · ', $parts);
    }

    /** ["tailored résumé"] for a match of a tailored résumé, else none. */
    private static function tailored_label(array $m): array {
        return str_ends_with((string) $m['cv_name'], '(tailored résumé)') ? ['tailored résumé'] : [];
    }

    /** What has been done for the match: written application, résumé, logged on Jobnet. */
    private static function written_labels(array $m): array {
        return array_keys(array_filter([
            'application' => (int) $m['has_application'] === 1,
            'résumé' => (int) $m['has_resume'] === 1,
            'on Jobnet' => $m['jobnet_logged_at'] !== null,
        ]));
    }
}
