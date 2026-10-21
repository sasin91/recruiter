<?php
require_once __DIR__ . '/Job_search_rules.php';

/**
 * The top of a job search's page (views/search.php): what it looks for, and
 * how its last scan went.
 */
class Job_search_summary {

    /** ['line' => ..., 'last_scan' => null or ['when', 'state', 'text', 'usage']]. */
    public static function from(array $search, ?array $last_run, array $tokens): array {
        return [
            'line' => self::line($search),
            'last_scan' => $last_run ? self::last_scan($last_run, $tokens) : null,
        ];
    }

    /** "php developer · Odense · remote too · on jobnet.dk · Every day · tailors from 60 · last scanned 21 Oct 2026 08:12". */
    private static function line(array $search): string {
        return implode(' · ', array_filter([
            (string) $search['keywords'],
            (string) $search['location'],
            (int) $search['remote_ok'] ? 'remote too' : '',
            $search['sites'] ? 'on ' . implode(', ', $search['sites']) : '',
            Job_search_rules::frequency((int) $search['scan_every_hours']),
            'tailors from ' . (int) $search['tailor_from_score'],
            self::last_scanned($search),
        ], fn($part) => $part !== ''));
    }

    /** "last scanned 21 Oct 2026 08:12", or "not scanned yet". */
    private static function last_scanned(array $search): string {
        return $search['last_scanned_at'] ? 'last scanned ' . date('j M Y H:i', (int) $search['last_scanned_at']) : 'not scanned yet';
    }

    /** The last run: when, its state (running, error, done), what it found, the month's token use. */
    private static function last_scan(array $run, array $tokens): array {
        return [
            'when' => date('j M H:i', (int) $run['started_at']),
            'state' => self::run_state($run),
            'text' => self::run_text($run),
            'usage' => 'Scans in the last 30 days: ' . (int) $tokens['scans'] . ', ' . number_format((int) $tokens['input_tokens'] + (int) $tokens['output_tokens']) . ' tokens.',
        ];
    }

    private static function run_state(array $run): string {
        if ($run['finished_at'] === null) {
            return 'running';
        }
        return $run['error'] !== '' ? 'error' : 'done';
    }

    /** "running…", the run's error, or "found 12, 3 new.". */
    private static function run_text(array $run): string {
        return match (self::run_state($run)) {
            'running' => 'running…',
            'error' => (string) $run['error'],
            'done' => 'found ' . (int) $run['posts_found'] . ', ' . (int) $run['posts_new'] . ' new.',
        };
    }
}
