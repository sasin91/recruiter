<?php
require_once __DIR__ . '/Job_queue.php';

/**
 * Queued jobs in an array, for tests. Behaves like Database_job_queue,
 * including the parameters' round trip through JSON.
 */
final class In_memory_job_queue implements Job_queue {

    /** @var array<int, Job> */
    public array $jobs = [];

    /** What has_live_worker() answers. */
    public bool $worker_alive = false;

    private int $next_id = 1;
    private Closure $clock;

    public function __construct(private readonly string $name = 'default', ?Closure $clock = null) {
        $this->clock = $clock ?? fn(): int => time();
    }

    public function enqueue(Job $job): Job {
        $now = ($this->clock)();
        $key = $job->unique_key;
        if ($key !== null && ($existing = $this->by_unique_keys([$key])[$key] ?? null) !== null) {
            if (!$existing->is_failed()) {
                return $existing;
            }
            return $this->jobs[$existing->id] = $existing->with(
                failed_at: null, attempts: 0, available_at: max($now, $job->available_at),
                reserved_at: null, reserved_by: null, queue: $this->name
            );
        }
        $id = $this->next_id++;
        return $this->jobs[$id] = $job->with(
            parameters: Job::parameters_from_json($job->parameters_json()),
            queue: $this->name,
            id: $id,
            available_at: max($now, $job->available_at),
            created_at: $now,
        );
    }

    public function dequeue(string $worker_id): ?Job {
        $now = ($this->clock)();
        $due = array_filter($this->jobs, fn(Job $e) => $e->is_waiting() && $e->available_at <= $now);
        if ($due === []) {
            return null;
        }
        uasort($due, fn(Job $a, Job $b) => [$a->available_at, $a->id] <=> [$b->available_at, $b->id]);
        $job = reset($due);
        return $this->jobs[$job->id] = $job->with(reserved_at: $now, reserved_by: $worker_id);
    }

    public function dequeue_id(int $id, string $worker_id): ?Job {
        $job = $this->jobs[$id] ?? null;
        if ($job === null || !$job->is_waiting()) {
            return null;
        }
        return $this->jobs[$id] = $job->with(reserved_at: ($this->clock)(), reserved_by: $worker_id);
    }

    public function ack(Job $job): void {
        unset($this->jobs[$job->id]);
    }

    public function retry(Job $job, Throwable $error, int $available_at): void {
        $this->jobs[$job->id] = $this->jobs[$job->id]->with(
            attempts: $job->attempts + 1, available_at: $available_at, reserved_at: null, reserved_by: null,
            error_class: get_class($error), error_message: Job::error_text($error)
        );
    }

    public function fail(Job $job, Throwable $error): void {
        $this->jobs[$job->id] = $this->jobs[$job->id]->with(
            attempts: $job->attempts + 1, failed_at: ($this->clock)(), reserved_at: null, reserved_by: null,
            error_class: get_class($error), error_message: Job::error_text($error)
        );
    }

    public function has_live_worker(int $seconds): bool {
        return $this->worker_alive;
    }

    public function find(int $id): ?Job {
        return $this->jobs[$id] ?? null;
    }

    public function list(string $which = 'all', int $limit = 50): array {
        $found = array_filter($this->jobs, fn(Job $e) => match ($which) {
            'failed' => $e->is_failed(),
            'waiting' => !$e->is_failed(),
            'all' => true,
            default => throw new InvalidArgumentException("Unknown list $which."),
        });
        return array_slice(array_values($found), 0, $limit);
    }

    public function by_unique_keys(array $keys): array {
        $found = [];
        foreach ($this->jobs as $job) {
            if ($job->unique_key !== null && in_array($job->unique_key, $keys, true)) {
                $found[$job->unique_key] = $job;
            }
        }
        return $found;
    }

    public function retry_failed(int $id): bool {
        $job = $this->jobs[$id] ?? null;
        if ($job === null || !$job->is_failed()) {
            return false;
        }
        $this->jobs[$id] = $job->with(failed_at: null, attempts: 0, available_at: ($this->clock)());
        return true;
    }

    public function remove(int $id): bool {
        $job = $this->jobs[$id] ?? null;
        if ($job === null || $job->is_running()) {
            return false;
        }
        unset($this->jobs[$id]);
        return true;
    }

    public function counts(): array {
        $counts = ['waiting' => 0, 'running' => 0, 'failed' => 0];
        foreach ($this->jobs as $job) {
            $counts[$job->is_failed() ? 'failed' : ($job->is_running() ? 'running' : 'waiting')]++;
        }
        return $counts;
    }
}
