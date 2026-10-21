<?php
require_once __DIR__ . '/Queue_job_row.php';

/**
 * A worker (queue_workers) as a row of queue/manage (views/manage.php).
 */
class Queue_worker_row {

    // A worker that hasn't been seen for this long has stopped answering.
    private const SILENT_AFTER = 60;

    /** ['id', 'host', 'queues', 'handled', 'failed', 'started', 'last_seen', 'stopped']. */
    public static function from(array $w): array {
        return [
            'id' => (string) $w['id'],
            'host' => $w['hostname'] . ' (' . (int) $w['process_id'] . ')',
            'queues' => (string) $w['queues'],
            'handled' => (int) $w['handled'],
            'failed' => (int) $w['failed'],
            'started' => Queue_job_row::when((int) $w['started_at']),
            'last_seen' => Queue_job_row::when((int) $w['last_seen_at']),
            'stopped' => self::stopped($w),
        ];
    }

    /** When it stopped, "running", or "stopped answering". */
    private static function stopped(array $w): string {
        if ($w['stopped_at'] !== null) {
            return Queue_job_row::when((int) $w['stopped_at']);
        }
        return (int) $w['last_seen_at'] >= time() - self::SILENT_AFTER ? 'running' : 'stopped answering';
    }
}
