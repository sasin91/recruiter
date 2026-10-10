<?php
require_once __DIR__ . '/Job.php';

/**
 * Where queued jobs wait for a worker. Database_job_queue keeps them in
 * MariaDB/MySQL; In_memory_job_queue in an array (tests).
 */
interface Job_queue {

    /**
     * Queues the job and returns it with its id. With a unique key
     * that is already waiting or running, returns that one unchanged; with
     * one that failed, queues it again.
     */
    public function enqueue(Job $job): Job;

    /** Reserves the next due job for this worker, or null when none is due. */
    public function dequeue(string $worker_id): ?Job;

    /**
     * Reserves this job (just enqueued) for $worker_id, or null when it is no
     * longer waiting: someone else has it. Used to handle a job in the
     * request when no worker is running.
     */
    public function dequeue_id(int $id, string $worker_id): ?Job;

    /** Done: the job ran and is removed. */
    public function ack(Job $job): void;

    /** Failed this time: back in the queue from $available_at, with the error kept. */
    public function retry(Job $job, Throwable $error, int $available_at): void;

    /** Failed for good: kept with the error until retried or removed. */
    public function fail(Job $job, Throwable $error): void;

    /** Whether a worker working on this queue was seen in the last $seconds. */
    public function has_live_worker(int $seconds): bool;

    // Inspecting and managing jobs (failed:show, the admin page) ---------

    /** One job by id, or null. */
    public function find(int $id): ?Job;

    /**
     * Jobs, oldest first: 'failed', 'waiting' (includes running) or 'all'.
     *
     * @return Job[]
     */
    public function list(string $which = 'all', int $limit = 50): array;

    /**
     * The jobs with these unique keys, as key => Job (missing keys
     * have none: never queued, or handled and gone).
     *
     * @param string[] $keys
     * @return array<string, Job>
     */
    public function by_unique_keys(array $keys): array;

    /** Queues a failed job again from now, attempts reset. False when it isn't failed. */
    public function retry_failed(int $id): bool;

    /** Deletes a job that isn't running. False when there is none. */
    public function remove(int $id): bool;

    /** Counts: ['waiting' => n, 'running' => n, 'failed' => n]. */
    public function counts(): array;
}
