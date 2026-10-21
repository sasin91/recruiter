<?php
require_once __DIR__ . '/Job_search_rules.php';

/**
 * A job search as a card of the user's list (views/index.php).
 */
class Job_search_card {

    /** ['id', 'name', 'chip' => null or ['label', 'class'], 'meta']. */
    public static function from(array $s): array {
        return [
            'id' => (int) $s['id'],
            'name' => (string) $s['name'],
            'chip' => self::chip($s),
            'meta' => self::meta_line($s),
        ];
    }

    /** Off, Paused (scans stopped, e.g. a rejected key), or none while it runs. */
    private static function chip(array $s): ?array {
        if (!(int) $s['active']) {
            return ['label' => 'Off', 'class' => ''];
        }
        return $s['paused_reason'] !== '' ? ['label' => 'Paused', 'class' => 'warn'] : null;
    }

    /** "php developer · Odense · Every day · 12 found, 4 matched · last scanned 21 Oct 08:12". */
    private static function meta_line(array $s): string {
        return implode(' · ', array_filter([
            (string) $s['keywords'],
            (string) $s['location'],
            Job_search_rules::frequency((int) $s['scan_every_hours']),
            (int) $s['posts'] . ' found, ' . (int) $s['matched'] . ' matched',
            $s['last_scanned_at'] ? 'last scanned ' . date('j M H:i', (int) $s['last_scanned_at']) : 'not scanned yet',
        ], fn($part) => $part !== ''));
    }
}
