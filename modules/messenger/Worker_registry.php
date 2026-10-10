<?php

/**
 * One messenger_workers row per worker process: which transports it
 * consumes, how many messages it handled and failed, and when it was last
 * seen. Message_bus asks it (through the transport) whether any worker is
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

    /** @param string[] $transports */
    public function start(string $worker_id, array $transports): void {
        $now = ($this->clock)();
        $this->db->prepare('DELETE FROM messenger_workers WHERE last_seen_at < ?')->execute([$now - self::KEEP_SECONDS]);
        $this->db->prepare(
            'INSERT INTO messenger_workers (id, hostname, process_id, transports, handled, failed, started_at, last_seen_at)
             VALUES (?, ?, ?, ?, 0, 0, ?, ?)'
        )->execute([$worker_id, mb_substr(gethostname() ?: 'unknown', 0, 255), getmypid() ?: 0, implode(',', $transports), $now, $now]);
    }

    public function beat(string $worker_id, int $handled, int $failed): void {
        $this->db->prepare('UPDATE messenger_workers SET handled = ?, failed = ?, last_seen_at = ? WHERE id = ?')
            ->execute([$handled, $failed, ($this->clock)(), $worker_id]);
    }

    public function stop(string $worker_id, int $handled, int $failed): void {
        $now = ($this->clock)();
        $this->db->prepare('UPDATE messenger_workers SET handled = ?, failed = ?, last_seen_at = ?, stopped_at = ? WHERE id = ?')
            ->execute([$handled, $failed, $now, $now, $worker_id]);
    }

    /** Workers, newest first, at most $limit. */
    public function list(int $limit = 20): array {
        $statement = $this->db->prepare(
            'SELECT id, hostname, process_id, transports, handled, failed, started_at, last_seen_at, stopped_at
             FROM messenger_workers ORDER BY started_at DESC LIMIT ?'
        );
        $statement->bindValue(1, $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
