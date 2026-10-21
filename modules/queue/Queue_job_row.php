<?php
require_once __DIR__ . '/Job.php';

/**
 * A queued job as a row of queue/manage (views/manage.php): failed jobs
 * with their error, waiting and running ones with their state.
 */
class Queue_job_row {

    /** ['id', 'label', 'attempts', 'failed', 'state', 'error_class', 'error', 'buttons' => [['url', 'label']]]. */
    public static function from(Job $job): array {
        return [
            'id' => (int) $job->id,
            'label' => $job->label(),
            'attempts' => $job->attempts,
            'failed' => self::when($job->failed_at),
            'state' => $job->is_running() ? 'Running since ' . self::when($job->reserved_at) : 'Due ' . self::when($job->available_at),
            'error_class' => (string) $job->error_class,
            'error' => (string) $job->error_message,
            'buttons' => self::buttons($job),
        ];
    }

    /** "20 Oct 09:00:00", or '' for no time. */
    public static function when(?int $t): string {
        return $t === null ? '' : date('j M H:i:s', $t);
    }

    /** A failed job can be retried or removed; a waiting one removed; a running one left alone. */
    private static function buttons(Job $job): array {
        $actions = match (true) {
            $job->is_failed() => ['retry' => 'Retry', 'remove' => 'Remove'],
            $job->is_running() => [],
            default => ['remove' => 'Remove'],
        };
        return array_map(
            fn(string $action, string $label) => ['url' => "queue/submit_$action/" . (int) $job->id, 'label' => $label],
            array_keys($actions),
            $actions
        );
    }
}
