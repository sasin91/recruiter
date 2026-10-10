<?php
require_once __DIR__ . '/Envelope.php';

/**
 * Where queued calls wait for a worker. Database_transport keeps them in
 * MariaDB/MySQL; In_memory_transport in an array (tests).
 */
interface Transport {

    /**
     * Queues the envelope and returns it with its id. With a dedupe key
     * that is already waiting or running, returns that one unchanged; with
     * one that failed, queues it again.
     */
    public function send(Envelope $envelope): Envelope;

    /** Claims the next due message for this worker, or null when none is due. */
    public function claim(string $worker_id): ?Envelope;

    /**
     * Claims this message (just sent) for $worker_id, or null when it is no
     * longer waiting: someone else has it. Used to handle a message in the
     * request when no worker is running.
     */
    public function claim_id(int $id, string $worker_id): ?Envelope;

    /** Handled: the message is done and removed. */
    public function ack(Envelope $envelope): void;

    /** Failed this time: back in the queue from $available_at, with the error kept. */
    public function retry(Envelope $envelope, Throwable $error, int $available_at): void;

    /** Failed for good: kept with the error until retried or removed. */
    public function fail(Envelope $envelope, Throwable $error): void;

    /** Whether a worker consuming this transport was seen in the last $seconds. */
    public function has_live_worker(int $seconds): bool;

    // Inspecting and managing messages (failed:show, the admin page) ---------

    /** One message by id, or null. */
    public function find(int $id): ?Envelope;

    /**
     * Messages, oldest first: 'failed', 'waiting' (includes running) or 'all'.
     *
     * @return Envelope[]
     */
    public function list(string $which = 'all', int $limit = 50): array;

    /**
     * The messages with these dedupe keys, as key => Envelope (missing keys
     * have none: never queued, or handled and gone).
     *
     * @param string[] $keys
     * @return array<string, Envelope>
     */
    public function by_dedupe_keys(array $keys): array;

    /** Queues a failed message again from now, attempts reset. False when it isn't failed. */
    public function retry_failed(int $id): bool;

    /** Deletes a message that isn't running. False when there is none. */
    public function remove(int $id): bool;

    /** Counts: ['waiting' => n, 'running' => n, 'failed' => n]. */
    public function counts(): array;
}
