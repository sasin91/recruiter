<?php

/**
 * One queue_workers row per worker process: which queues it
 * works on, how many jobs it handled and failed, and when it was last
 * seen. Dispatcher asks it (through the queue) whether any worker is
 * running; the admin page lists the rows.
 */
final class Worker_registry {

    /** Stopped or vanished workers' rows are deleted after this long. */
    const KEEP_SECONDS = 7 * 86400;

    private Closure $clock;

    public function __construct(private readonly PDO $db, ?Closure $clock = null) {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->clock = $clock ?? fn(): int => time();
    }

    /** @param string[] $queues */
    public function start(string $worker_id, array $queues): void {
        $now = ($this->clock)();
        $this->db->prepare('DELETE FROM queue_workers WHERE last_seen_at < ?')->execute([$now - self::KEEP_SECONDS]);
        $this->db->prepare(
            'INSERT INTO queue_workers (id, hostname, process_id, queues, handled, failed, started_at, last_seen_at)
             VALUES (?, ?, ?, ?, 0, 0, ?, ?)'
        )->execute([$worker_id, mb_substr(gethostname() ?: 'unknown', 0, 255), getmypid() ?: 0, implode(',', $queues), $now, $now]);
    }

    public function beat(string $worker_id, int $handled, int $failed): void {
        $this->db->prepare('UPDATE queue_workers SET handled = ?, failed = ?, last_seen_at = ? WHERE id = ?')
            ->execute([$handled, $failed, ($this->clock)(), $worker_id]);
    }

    public function stop(string $worker_id, int $handled, int $failed): void {
        $now = ($this->clock)();
        $this->db->prepare('UPDATE queue_workers SET handled = ?, failed = ?, last_seen_at = ?, stopped_at = ? WHERE id = ?')
            ->execute([$handled, $failed, $now, $now, $worker_id]);
    }

    /** Workers, newest first, at most $limit. */
    public function list(int $limit = 20): array {
        $statement = $this->db->prepare(
            'SELECT id, hostname, process_id, queues, handled, failed, started_at, last_seen_at, stopped_at
             FROM queue_workers ORDER BY started_at DESC LIMIT ?'
        );
        $statement->bindValue(1, $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
