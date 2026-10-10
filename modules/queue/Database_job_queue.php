<?php
require_once __DIR__ . '/Job_queue.php';

/**
 * Queued jobs in MariaDB/MySQL: queue_jobs (sql/queue.sql). Several queues share the tables, told apart by
 * the queue column. Plain PDO, so it works without the framework.
 *
 * A worker reserves a job with one UPDATE ... ORDER BY ... LIMIT 1 that
 * writes its id into reserved_by, then reads the row back by that id, so
 * two workers never get the same job. A job reserved longer ago than
 * $visibility_timeout seconds and still there belonged to a worker that died;
 * it is queued again.
 */
final class Database_job_queue implements Job_queue {

    private const COLUMNS = 'id, queue, method, parameters, unique_key, available_at, attempts, reserved_at,
        reserved_by, failed_at, error_class, error_message, created_at';

    private Closure $clock;
    private int $next_timeout_check = 0;

    public function __construct(
        private readonly PDO $db,
        private readonly string $name = 'default',
        private readonly int $visibility_timeout = 3600,
        ?Closure $clock = null,
    ) {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->clock = $clock ?? fn(): int => time();
    }

    public function enqueue(Job $job): Job {
        $now = ($this->clock)();
        $key = $job->unique_key;
        $this->db->beginTransaction();
        try {
            if ($key !== null) {
                $existing = $this->row('unique_key = ? FOR UPDATE', [$key]);
                if ($existing !== null) {
                    if ($existing['failed_at'] === null) {
                        $this->db->commit();
                        return $this->job($existing);
                    }
                    $this->run(
                        'UPDATE queue_jobs SET failed_at = NULL, attempts = 0, available_at = ?,
                            reserved_at = NULL, reserved_by = NULL, queue = ? WHERE id = ?',
                        [max($now, $job->available_at), $this->name, (int) $existing['id']]
                    );
                    $this->db->commit();
                    return $this->find((int) $existing['id']);
                }
            }
            $this->run(
                'INSERT INTO queue_jobs (queue, method, parameters, unique_key, available_at, attempts, created_at)
                 VALUES (?, ?, ?, ?, ?, 0, ?)',
                [$this->name, $job->method, $job->parameters_json(), $key, max($now, $job->available_at), $now]
            );
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $job->with(queue: $this->name, id: $id, available_at: max($now, $job->available_at), created_at: $now);
    }

    public function dequeue(string $worker_id): ?Job {
        $now = ($this->clock)();
        if ($now >= $this->next_timeout_check) {
            $this->run(
                'UPDATE queue_jobs SET reserved_at = NULL, reserved_by = NULL
                 WHERE queue = ? AND failed_at IS NULL AND reserved_at < ?',
                [$this->name, $now - $this->visibility_timeout]
            );
            $this->next_timeout_check = $now + 60;
        }
        $reserved = $this->run(
            'UPDATE queue_jobs SET reserved_at = ?, reserved_by = ?
             WHERE queue = ? AND failed_at IS NULL AND reserved_at IS NULL AND available_at <= ?
             ORDER BY available_at, id LIMIT 1',
            [$now, $worker_id, $this->name, $now]
        )->rowCount();
        if ($reserved === 0) {
            return null;
        }
        $row = $this->row('reserved_by = ? AND failed_at IS NULL ORDER BY reserved_at DESC, id DESC LIMIT 1', [$worker_id]);
        return $row === null ? null : $this->job($row);
    }

    public function dequeue_id(int $id, string $worker_id): ?Job {
        $reserved = $this->run(
            'UPDATE queue_jobs SET reserved_at = ?, reserved_by = ?
             WHERE id = ? AND queue = ? AND failed_at IS NULL AND reserved_at IS NULL',
            [($this->clock)(), $worker_id, $id, $this->name]
        )->rowCount();
        return $reserved === 0 ? null : $this->find($id);
    }

    public function ack(Job $job): void {
        $this->run('DELETE FROM queue_jobs WHERE id = ?', [$this->id($job)]);
    }

    public function retry(Job $job, Throwable $error, int $available_at): void {
        $this->run(
            'UPDATE queue_jobs SET attempts = attempts + 1, available_at = ?, reserved_at = NULL,
                reserved_by = NULL, error_class = ?, error_message = ? WHERE id = ?',
            [$available_at, get_class($error), Job::error_text($error), $this->id($job)]
        );
    }

    public function fail(Job $job, Throwable $error): void {
        $this->run(
            'UPDATE queue_jobs SET attempts = attempts + 1, failed_at = ?, reserved_at = NULL,
                reserved_by = NULL, error_class = ?, error_message = ? WHERE id = ?',
            [($this->clock)(), get_class($error), Job::error_text($error), $this->id($job)]
        );
    }

    public function has_live_worker(int $seconds): bool {
        return (bool) $this->run(
            'SELECT 1 FROM queue_workers
             WHERE stopped_at IS NULL AND last_seen_at >= ? AND FIND_IN_SET(?, queues) LIMIT 1',
            [($this->clock)() - $seconds, $this->name]
        )->fetchColumn();
    }

    public function find(int $id): ?Job {
        $row = $this->row('id = ?', [$id]);
        return $row === null ? null : $this->job($row);
    }

    public function list(string $which = 'all', int $limit = 50): array {
        $where = match ($which) {
            'failed' => 'failed_at IS NOT NULL',
            'waiting' => 'failed_at IS NULL',
            'all' => '1 = 1',
            default => throw new InvalidArgumentException("Unknown list $which."),
        };
        $rows = $this->run(
            'SELECT ' . self::COLUMNS . " FROM queue_jobs WHERE queue = ? AND $where ORDER BY created_at, id LIMIT ?",
            [$this->name, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row) => $this->job($row), $rows);
    }

    public function by_unique_keys(array $keys): array {
        $keys = array_values(array_unique(array_map('strval', $keys)));
        if ($keys === []) {
            return [];
        }
        $marks = implode(', ', array_fill(0, count($keys), '?'));
        $found = [];
        foreach ($this->run('SELECT ' . self::COLUMNS . " FROM queue_jobs WHERE unique_key IN ($marks)", $keys)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $found[$row['unique_key']] = $this->job($row);
        }
        return $found;
    }

    public function retry_failed(int $id): bool {
        return $this->run(
            'UPDATE queue_jobs SET failed_at = NULL, attempts = 0, available_at = ?
             WHERE id = ? AND queue = ? AND failed_at IS NOT NULL',
            [($this->clock)(), $id, $this->name]
        )->rowCount() > 0;
    }

    public function remove(int $id): bool {
        return $this->run(
            'DELETE FROM queue_jobs WHERE id = ? AND queue = ? AND (reserved_at IS NULL OR failed_at IS NOT NULL)',
            [$id, $this->name]
        )->rowCount() > 0;
    }

    public function counts(): array {
        $row = $this->run(
            'SELECT SUM(failed_at IS NULL AND reserved_at IS NULL) AS waiting,
                    SUM(failed_at IS NULL AND reserved_at IS NOT NULL) AS running,
                    SUM(failed_at IS NOT NULL) AS failed
             FROM queue_jobs WHERE queue = ?',
            [$this->name]
        )->fetch(PDO::FETCH_ASSOC);
        return array_map(fn($n) => (int) $n, $row ?: ['waiting' => 0, 'running' => 0, 'failed' => 0]);
    }

    // -----------------------------------------------------------------

    private function id(Job $job): int {
        if ($job->id === null) {
            throw new LogicException('This job was never sent to a queue.');
        }
        return $job->id;
    }

    private function row(string $where, array $params): ?array {
        $row = $this->run('SELECT ' . self::COLUMNS . " FROM queue_jobs WHERE $where", $params)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function job(array $row): Job {
        $int = fn($value) => $value === null ? null : (int) $value;
        return new Job(
            method: $row['method'],
            parameters: Job::parameters_from_json((string) $row['parameters']),
            queue: $row['queue'],
            id: (int) $row['id'],
            unique_key: $row['unique_key'],
            available_at: (int) $row['available_at'],
            attempts: (int) $row['attempts'],
            reserved_at: $int($row['reserved_at']),
            reserved_by: $row['reserved_by'],
            failed_at: $int($row['failed_at']),
            error_class: $row['error_class'],
            error_message: $row['error_message'],
            created_at: (int) $row['created_at'],
        );
    }

    /** Prepares and runs SQL, binding each value by its PHP type (ints work in LIMIT). */
    private function run(string $sql, array $params = []): PDOStatement {
        $statement = $this->db->prepare($sql);
        foreach (array_values($params) as $i => $value) {
            $statement->bindValue($i + 1, $value, match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            });
        }
        $statement->execute();
        return $statement;
    }
}

