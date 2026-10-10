<?php
require_once __DIR__ . '/Job.php';
require_once __DIR__ . '/Unrecoverable_job_exception.php';

/**
 * When a failed job is tried again: exponential backoff.
 * With the defaults a job is tried 4 times in all, 1 s, 2 s and 4 s
 * apart, then it fails for good. In seconds, as the queue's timestamps are.
 */
final class Retry_policy {

    public function __construct(
        public readonly int $max_retries = 3,
        public readonly int $delay = 1,
        public readonly float $multiplier = 2,
        public readonly int $max_delay = 3600,
    ) {
    }

    /** From a queue's config: max_retries, delay, multiplier, max_delay. */
    public static function from_config(array $config): self {
        return new self(
            (int) ($config['max_retries'] ?? 3),
            (int) ($config['delay'] ?? 1),
            (float) ($config['multiplier'] ?? 2),
            (int) ($config['max_delay'] ?? 3600),
        );
    }

    /** Whether the job gets another try after this error. */
    public function is_retryable(Job $job, Throwable $error): bool {
        return !$error instanceof Unrecoverable_job_exception && $job->attempts < $this->max_retries;
    }

    /** Seconds to wait before the next try. */
    public function wait(Job $job): int {
        return (int) min($this->max_delay, ceil($this->delay * $this->multiplier ** $job->attempts));
    }
}
