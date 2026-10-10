<?php
require_once __DIR__ . '/Dispatcher.php';
require_once __DIR__ . '/Retry_policy.php';
require_once __DIR__ . '/Worker_registry.php';

/**
 * Works through queued jobs: reserves the next due one from its queues (in
 * the order given, so the first is the most urgent), runs it, and
 * removes it, or schedules a retry, or marks it failed (Retry_policy).
 *
 * Stops after the current job on SIGTERM or SIGINT, or when a limit in
 * run() is reached. Under a process manager (Kubernetes, systemd) a worker
 * is meant to stop now and then and be started again: --time-limit keeps
 * memory leaks and stale code in check.
 */
final class Worker {

    /** Seconds between heartbeats in queue_workers. */
    const HEARTBEAT_SECONDS = 5;

    public readonly string $id;
    public bool $running = false;
    public int $handled = 0;
    public int $failed = 0;

    private Closure $log;
    private Closure $clock;

    /**
     * @param array<string, Job_queue> $queues name => queue, most urgent first
     * @param array<string, Retry_policy> $retry_policies name => policy (default: Retry_policy's defaults)
     */
    public function __construct(
        private readonly array $queues,
        private readonly Dispatcher $dispatcher,
        private readonly array $retry_policies = [],
        private readonly ?Worker_registry $registry = null,
        ?Closure $log = null,
        ?Closure $clock = null,
    ) {
        $this->id = bin2hex(random_bytes(8));
        $this->log = $log ?? fn(string $line) => error_log($line);
        $this->clock = $clock ?? fn(): int => time();
    }

    /**
     * Runs until stopped. Options:
     *   limit            stop after this many jobs (0 = no limit)
     *   time_limit       stop after this many seconds (0 = no limit)
     *   memory_limit     stop when memory use passes this many bytes (0 = no limit)
     *   sleep            seconds to wait when nothing is due (default 1)
     *   stop_when_empty  stop as soon as nothing is due
     *
     * @return int jobs handled
     */
    public function run(array $options = []): int {
        $limit = (int) ($options['limit'] ?? 0);
        $time_limit = (int) ($options['time_limit'] ?? 0);
        $memory_limit = (int) ($options['memory_limit'] ?? 0);
        $sleep = max(0, (float) ($options['sleep'] ?? 1));
        $stop_when_empty = (bool) ($options['stop_when_empty'] ?? false);

        $started = ($this->clock)();
        $next_beat = $started + self::HEARTBEAT_SECONDS;
        $this->running = true;
        $this->listen_for_signals();
        $this->registry?->start($this->id, array_keys($this->queues));
        ($this->log)("Queue worker {$this->id} working on " . implode(', ', array_keys($this->queues)) . '.');

        try {
            while ($this->running) {
                $job = null;
                foreach ($this->queues as $name => $queue) {
                    if ($job = $queue->dequeue($this->id)) {
                        $this->process($name, $queue, $job);
                        break;
                    }
                }
                $now = ($this->clock)();
                if ($now >= $next_beat) {
                    $this->registry?->beat($this->id, $this->handled, $this->failed);
                    $next_beat = $now + self::HEARTBEAT_SECONDS;
                }
                if (($limit > 0 && $this->handled + $this->failed >= $limit)
                    || ($time_limit > 0 && $now - $started >= $time_limit)
                    || ($memory_limit > 0 && memory_get_usage(true) > $memory_limit)
                    || ($job === null && $stop_when_empty)) {
                    break;
                }
                if ($job === null && $this->running && $sleep > 0) {
                    usleep((int) ($sleep * 1_000_000));
                }
            }
        } finally {
            $this->running = false;
            $this->registry?->stop($this->id, $this->handled, $this->failed);
            ($this->log)("Queue worker {$this->id} stopped: {$this->handled} handled, {$this->failed} failed.");
        }
        return $this->handled;
    }

    /** Stops after the current job. */
    public function stop(): void {
        $this->running = false;
    }

    private function process(string $name, Job_queue $queue, Job $job): void {
        $label = "{$job->label()} (#{$job->id})";
        try {
            $this->dispatcher->handle($job);
        } catch (Throwable $e) {
            $policy = $this->retry_policies[$name] ?? new Retry_policy();
            if ($policy->is_retryable($job, $e)) {
                $wait = $policy->wait($job);
                $queue->retry($job, $e, ($this->clock)() + $wait);
                ($this->log)("Queue: $label failed (try " . ($job->attempts + 1) . "), retrying in {$wait}s: " . $e->getMessage());
            } else {
                $queue->fail($job, $e);
                $this->failed++;
                ($this->log)("Queue: $label failed for good: " . get_class($e) . ': ' . $e->getMessage());
            }
            return;
        }
        $queue->ack($job);
        $this->handled++;
    }

    private function listen_for_signals(): void {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }
        pcntl_async_signals(true);
        foreach ([SIGTERM, SIGINT] as $signal) {
            pcntl_signal($signal, function () {
                $this->running = false;
            });
        }
    }
}
