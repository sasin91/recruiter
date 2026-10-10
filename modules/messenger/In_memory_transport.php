<?php
require_once __DIR__ . '/Transport.php';
require_once __DIR__ . '/Message_fields.php';

/**
 * Messages in an array, for tests. Behaves like Database_transport,
 * including the round trip through Message_fields, so a message that
 * wouldn't survive the database doesn't survive here either.
 */
final class In_memory_transport implements Transport {

    /** @var array<int, Envelope> */
    public array $messages = [];

    /** What has_live_worker() answers. */
    public bool $worker_alive = false;

    private int $next_id = 1;
    private Closure $clock;

    public function __construct(private readonly string $name = 'async', ?Closure $clock = null) {
        $this->clock = $clock ?? fn(): int => time();
    }

    public function send(Envelope $envelope): Envelope {
        $now = ($this->clock)();
        $fields = Message_fields::from_message($envelope->message);
        $key = $envelope->dedupe_key;
        if ($key !== null && ($existing = $this->by_dedupe_keys([$key])[$key] ?? null) !== null) {
            if (!$existing->is_failed()) {
                return $existing;
            }
            return $this->messages[$existing->id] = $existing->with(
                failed_at: null, attempts: 0, available_at: max($now, $envelope->available_at),
                delivered_at: null, delivered_to: null, transport: $this->name
            );
        }
        $id = $this->next_id++;
        return $this->messages[$id] = $envelope->with(
            message: Message_fields::to_message($envelope->message_class(), $fields),
            transport: $this->name,
            id: $id,
            available_at: max($now, $envelope->available_at),
            created_at: $now,
        );
    }

    public function claim(string $worker_id): ?Envelope {
        $now = ($this->clock)();
        $due = array_filter($this->messages, fn(Envelope $e) => $e->is_waiting() && $e->available_at <= $now);
        if ($due === []) {
            return null;
        }
        uasort($due, fn(Envelope $a, Envelope $b) => [$a->available_at, $a->id] <=> [$b->available_at, $b->id]);
        $envelope = reset($due);
        return $this->messages[$envelope->id] = $envelope->with(delivered_at: $now, delivered_to: $worker_id);
    }

    public function claim_id(int $id, string $worker_id): ?Envelope {
        $envelope = $this->messages[$id] ?? null;
        if ($envelope === null || !$envelope->is_waiting()) {
            return null;
        }
        return $this->messages[$id] = $envelope->with(delivered_at: ($this->clock)(), delivered_to: $worker_id);
    }

    public function ack(Envelope $envelope): void {
        unset($this->messages[$envelope->id]);
    }

    public function retry(Envelope $envelope, Throwable $error, int $available_at): void {
        $this->messages[$envelope->id] = $this->messages[$envelope->id]->with(
            attempts: $envelope->attempts + 1, available_at: $available_at, delivered_at: null, delivered_to: null,
            error_class: get_class($error), error_message: Envelope::error_text($error)
        );
    }

    public function fail(Envelope $envelope, Throwable $error): void {
        $this->messages[$envelope->id] = $this->messages[$envelope->id]->with(
            attempts: $envelope->attempts + 1, failed_at: ($this->clock)(), delivered_at: null, delivered_to: null,
            error_class: get_class($error), error_message: Envelope::error_text($error)
        );
    }

    public function has_live_worker(int $seconds): bool {
        return $this->worker_alive;
    }

    public function find(int $id): ?Envelope {
        return $this->messages[$id] ?? null;
    }

    public function list(string $which = 'all', int $limit = 50): array {
        $found = array_filter($this->messages, fn(Envelope $e) => match ($which) {
            'failed' => $e->is_failed(),
            'waiting' => !$e->is_failed(),
            'all' => true,
            default => throw new InvalidArgumentException("Unknown list $which."),
        });
        return array_slice(array_values($found), 0, $limit);
    }

    public function by_dedupe_keys(array $keys): array {
        $found = [];
        foreach ($this->messages as $envelope) {
            if ($envelope->dedupe_key !== null && in_array($envelope->dedupe_key, $keys, true)) {
                $found[$envelope->dedupe_key] = $envelope;
            }
        }
        return $found;
    }

    public function retry_failed(int $id): bool {
        $envelope = $this->messages[$id] ?? null;
        if ($envelope === null || !$envelope->is_failed()) {
            return false;
        }
        $this->messages[$id] = $envelope->with(failed_at: null, attempts: 0, available_at: ($this->clock)());
        return true;
    }

    public function remove(int $id): bool {
        $envelope = $this->messages[$id] ?? null;
        if ($envelope === null || $envelope->is_running()) {
            return false;
        }
        unset($this->messages[$id]);
        return true;
    }

    public function counts(): array {
        $counts = ['waiting' => 0, 'running' => 0, 'failed' => 0];
        foreach ($this->messages as $envelope) {
            $counts[$envelope->is_failed() ? 'failed' : ($envelope->is_running() ? 'running' : 'waiting')]++;
        }
        return $counts;
    }
}
