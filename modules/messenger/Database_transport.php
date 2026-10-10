<?php
require_once __DIR__ . '/Transport.php';

/**
 * Queued calls in MariaDB/MySQL: messenger_messages and
 * messenger_message_arguments (sql/messenger.sql). Several transports share the tables, told apart by
 * the transport column. Plain PDO, so it works without the framework.
 *
 * A worker claims a message with one UPDATE ... ORDER BY ... LIMIT 1 that
 * writes its id into delivered_to, then reads the row back by that id, so
 * two workers never get the same message. A message claimed longer ago than
 * $redeliver_after seconds and still there belonged to a worker that died;
 * it is queued again.
 */
final class Database_transport implements Transport {

    private const COLUMNS = 'id, transport, target, dedupe_key, available_at, attempts, delivered_at,
        delivered_to, failed_at, error_class, error_message, created_at';

    private Closure $clock;
    private int $next_redelivery_check = 0;

    public function __construct(
        private readonly PDO $db,
        private readonly string $name = 'async',
        private readonly int $redeliver_after = 3600,
        ?Closure $clock = null,
    ) {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->clock = $clock ?? fn(): int => time();
    }

    public function send(Envelope $envelope): Envelope {
        $now = ($this->clock)();
        $key = $envelope->dedupe_key;
        $this->db->beginTransaction();
        try {
            if ($key !== null) {
                $existing = $this->row('dedupe_key = ? FOR UPDATE', [$key]);
                if ($existing !== null) {
                    if ($existing['failed_at'] === null) {
                        $this->db->commit();
                        return $this->envelope($existing);
                    }
                    $this->run(
                        'UPDATE messenger_messages SET failed_at = NULL, attempts = 0, available_at = ?,
                            delivered_at = NULL, delivered_to = NULL, transport = ? WHERE id = ?',
                        [max($now, $envelope->available_at), $this->name, (int) $existing['id']]
                    );
                    $this->db->commit();
                    return $this->find((int) $existing['id']);
                }
            }
            $this->run(
                'INSERT INTO messenger_messages (transport, target, dedupe_key, available_at, attempts, created_at)
                 VALUES (?, ?, ?, ?, 0, ?)',
                [$this->name, $envelope->target, $key, max($now, $envelope->available_at), $now]
            );
            $id = (int) $this->db->lastInsertId();
            foreach ($envelope->arguments as $position => $argument) {
                [$type, $value] = Envelope::store_argument($argument);
                $this->run(
                    'INSERT INTO messenger_message_arguments (messenger_message_id, position, value_type, value) VALUES (?, ?, ?, ?)',
                    [$id, $position, $type, $value]
                );
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $envelope->with(transport: $this->name, id: $id, available_at: max($now, $envelope->available_at), created_at: $now);
    }

    public function claim(string $worker_id): ?Envelope {
        $now = ($this->clock)();
        if ($now >= $this->next_redelivery_check) {
            $this->run(
                'UPDATE messenger_messages SET delivered_at = NULL, delivered_to = NULL
                 WHERE transport = ? AND failed_at IS NULL AND delivered_at < ?',
                [$this->name, $now - $this->redeliver_after]
            );
            $this->next_redelivery_check = $now + 60;
        }
        $claimed = $this->run(
            'UPDATE messenger_messages SET delivered_at = ?, delivered_to = ?
             WHERE transport = ? AND failed_at IS NULL AND delivered_at IS NULL AND available_at <= ?
             ORDER BY available_at, id LIMIT 1',
            [$now, $worker_id, $this->name, $now]
        )->rowCount();
        if ($claimed === 0) {
            return null;
        }
        $row = $this->row('delivered_to = ? AND failed_at IS NULL ORDER BY delivered_at DESC, id DESC LIMIT 1', [$worker_id]);
        return $row === null ? null : $this->envelope($row);
    }

    public function claim_id(int $id, string $worker_id): ?Envelope {
        $claimed = $this->run(
            'UPDATE messenger_messages SET delivered_at = ?, delivered_to = ?
             WHERE id = ? AND transport = ? AND failed_at IS NULL AND delivered_at IS NULL',
            [($this->clock)(), $worker_id, $id, $this->name]
        )->rowCount();
        return $claimed === 0 ? null : $this->find($id);
    }

    public function ack(Envelope $envelope): void {
        $this->run('DELETE FROM messenger_messages WHERE id = ?', [$this->id($envelope)]);
    }

    public function retry(Envelope $envelope, Throwable $error, int $available_at): void {
        $this->run(
            'UPDATE messenger_messages SET attempts = attempts + 1, available_at = ?, delivered_at = NULL,
                delivered_to = NULL, error_class = ?, error_message = ? WHERE id = ?',
            [$available_at, get_class($error), Envelope::error_text($error), $this->id($envelope)]
        );
    }

    public function fail(Envelope $envelope, Throwable $error): void {
        $this->run(
            'UPDATE messenger_messages SET attempts = attempts + 1, failed_at = ?, delivered_at = NULL,
                delivered_to = NULL, error_class = ?, error_message = ? WHERE id = ?',
            [($this->clock)(), get_class($error), Envelope::error_text($error), $this->id($envelope)]
        );
    }

    public function has_live_worker(int $seconds): bool {
        return (bool) $this->run(
            'SELECT 1 FROM messenger_workers
             WHERE stopped_at IS NULL AND last_seen_at >= ? AND FIND_IN_SET(?, transports) LIMIT 1',
            [($this->clock)() - $seconds, $this->name]
        )->fetchColumn();
    }

    public function find(int $id): ?Envelope {
        $row = $this->row('id = ?', [$id]);
        return $row === null ? null : $this->envelope($row);
    }

    public function list(string $which = 'all', int $limit = 50): array {
        $where = match ($which) {
            'failed' => 'failed_at IS NOT NULL',
            'waiting' => 'failed_at IS NULL',
            'all' => '1 = 1',
            default => throw new InvalidArgumentException("Unknown list $which."),
        };
        $rows = $this->run(
            'SELECT ' . self::COLUMNS . " FROM messenger_messages WHERE transport = ? AND $where ORDER BY created_at, id LIMIT ?",
            [$this->name, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row) => $this->envelope($row), $rows);
    }

    public function by_dedupe_keys(array $keys): array {
        $keys = array_values(array_unique(array_map('strval', $keys)));
        if ($keys === []) {
            return [];
        }
        $marks = implode(', ', array_fill(0, count($keys), '?'));
        $found = [];
        foreach ($this->run('SELECT ' . self::COLUMNS . " FROM messenger_messages WHERE dedupe_key IN ($marks)", $keys)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $found[$row['dedupe_key']] = $this->envelope($row);
        }
        return $found;
    }

    public function retry_failed(int $id): bool {
        return $this->run(
            'UPDATE messenger_messages SET failed_at = NULL, attempts = 0, available_at = ?
             WHERE id = ? AND transport = ? AND failed_at IS NOT NULL',
            [($this->clock)(), $id, $this->name]
        )->rowCount() > 0;
    }

    public function remove(int $id): bool {
        return $this->run(
            'DELETE FROM messenger_messages WHERE id = ? AND transport = ? AND (delivered_at IS NULL OR failed_at IS NOT NULL)',
            [$id, $this->name]
        )->rowCount() > 0;
    }

    public function counts(): array {
        $row = $this->run(
            'SELECT SUM(failed_at IS NULL AND delivered_at IS NULL) AS waiting,
                    SUM(failed_at IS NULL AND delivered_at IS NOT NULL) AS running,
                    SUM(failed_at IS NOT NULL) AS failed
             FROM messenger_messages WHERE transport = ?',
            [$this->name]
        )->fetch(PDO::FETCH_ASSOC);
        return array_map(fn($n) => (int) $n, $row ?: ['waiting' => 0, 'running' => 0, 'failed' => 0]);
    }

    // -----------------------------------------------------------------

    private function id(Envelope $envelope): int {
        if ($envelope->id === null) {
            throw new LogicException('This envelope was never sent to a transport.');
        }
        return $envelope->id;
    }

    private function row(string $where, array $params): ?array {
        $row = $this->run('SELECT ' . self::COLUMNS . " FROM messenger_messages WHERE $where", $params)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function envelope(array $row): Envelope {
        $arguments = [];
        foreach ($this->run(
            'SELECT value_type, value FROM messenger_message_arguments WHERE messenger_message_id = ? ORDER BY position',
            [(int) $row['id']]
        )->fetchAll(PDO::FETCH_ASSOC) as $argument) {
            $arguments[] = Envelope::load_argument($argument['value_type'], $argument['value']);
        }
        $int = fn($value) => $value === null ? null : (int) $value;
        return new Envelope(
            target: $row['target'],
            arguments: $arguments,
            transport: $row['transport'],
            id: (int) $row['id'],
            dedupe_key: $row['dedupe_key'],
            available_at: (int) $row['available_at'],
            attempts: (int) $row['attempts'],
            delivered_at: $int($row['delivered_at']),
            delivered_to: $row['delivered_to'],
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

