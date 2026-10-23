<?php
require_once __DIR__ . '/Match_score.php';
require_once __DIR__ . '/Joblog.php';
require_once __DIR__ . '/Reject_reason.php';

/**
 * A saved match (Cv_match_model::recent) as a row of the CV checker's list
 * (views/history.php): its id, tag, title and meta line, ready to print.
 * The date in the meta line is printed in the server's time zone and put in
 * the reader's own by the page (js/app.js), so `saved_at` gives the moment.
 */
class Saved_match_row {

    /**
     * ['id' => 7, 'tag' => 'good', 'title' => ..., 'about' => "81% good",
     * 'saved_at' => "2026-10-21T22:30:00Z", 'saved_on' => "21 Oct 2026",
     * 'done' => "application · applied · on Jobnet"]: the meta line is about, saved_on
     * and done (when there is any), which ends with "rejected: Too long
     * commute" for a rejected match. about ends with "likely disqualifier:
     * Located close to Copenhagen" when the match misses a location the
     * post asks for.
     */
    public static function from(array $m): array {
        return [
            'id' => (int) $m['id'],
            'tag' => (string) $m['tag'],
            'title' => self::title($m),
            'about' => implode(' · ', [Match_score::short_label($m['score'], (string) $m['tag']), ...self::tailored_label($m), ...self::disqualifier_label($m)]),
            'saved_at' => gmdate('Y-m-d\\TH:i:s\\Z', (int) $m['created_at']),
            'saved_on' => date('j M Y', (int) $m['created_at']),
            'done' => implode(' · ', [...self::written_labels($m), ...self::rejected_label($m)]),
        ];
    }

    /** "Job title · Company", or "Untitled" when the post named neither. */
    private static function title(array $m): string {
        $title = implode(' · ', array_filter([(string) $m['job_title'], (string) $m['company']]));
        return $title !== '' ? $title : 'Untitled';
    }

    /** ["tailored résumé"] for a match of a tailored résumé, else none. */
    private static function tailored_label(array $m): array {
        return str_ends_with((string) $m['cv_name'], '(tailored résumé)') ? ['tailored résumé'] : [];
    }

    /** ["likely disqualifier: Located close to Copenhagen"] for a missed location, else none. */
    private static function disqualifier_label(array $m): array {
        return ($m['location_missed'] ?? null) !== null ? ['likely disqualifier: ' . $m['location_missed']] : [];
    }

    /** What has been done for the match: written application, résumé, applied, logged on Jobnet. */
    private static function written_labels(array $m): array {
        return array_keys(array_filter([
            'application' => (int) $m['has_application'] === 1,
            'résumé' => (int) $m['has_resume'] === 1,
            'applied' => in_array($m['jobnet_status'] ?? null, Joblog::APPLIED_STATUSES, true),
            'on Jobnet' => $m['jobnet_logged_at'] !== null,
        ]));
    }

    /** ["rejected: Too long commute (1h each way)"] (or just "rejected") for a rejected match, else none. */
    private static function rejected_label(array $m): array {
        if (($m['rejected_at'] ?? null) === null) {
            return [];
        }
        $why = Reject_reason::describe($m['reject_reason'] ?? null, (string) ($m['reject_note'] ?? ''));
        return [$why !== '' ? "rejected: $why" : 'rejected'];
    }
}
