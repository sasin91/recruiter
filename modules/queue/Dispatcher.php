<?php
require_once __DIR__ . '/Job.php';
require_once __DIR__ . '/Job_queue.php';
require_once __DIR__ . '/Unrecoverable_job_exception.php';

/**
 * Queues jobs ("run Applications::_score(application_id: 42) later") on
 * the queue the routing names for the method, or on the default
 * queue. 'sync' runs the job at once, in this request.
 *
 * $runner runs the job: (string $method, array $parameters) => result. In
 * a Trongate app it loads the controller and calls the method with named
 * parameters (Queue); a framework-free app passes its own.
 *
 * $checker says what doesn't fit the method's current signature:
 * (string $method, array $parameters) => string[] (Call_signature). A job
 * that doesn't fit is refused when queued, and fails without retries when
 * it was queued under an older signature.
 *
 * When no worker has been seen on the queue for $in_request_without_worker
 * seconds, a job due now is run in the request after all, so the app keeps
 * working while its worker is down or not deployed yet. If it fails there,
 * it is marked failed at once (nobody would run the retries) and dispatch()
 * returns the failed job; it doesn't throw.
 */
final class Dispatcher {

    const SYNC = 'sync';

    private Closure $log;

    /**
     * @param array<string, string> $routing method => queue name or 'sync'
     * @param array<string, Job_queue> $queues the first is the default
     * @param Closure(string, array): mixed $runner
     * @param (Closure(string, array): string[])|null $checker
     */
    public function __construct(
        private readonly array $routing,
        private readonly array $queues,
        private readonly Closure $runner,
        private readonly int $in_request_without_worker = 60,
        ?Closure $log = null,
        private readonly ?Closure $checker = null,
    ) {
        $this->log = $log ?? fn(string $line) => error_log($line);
    }

    /**
     * Queues $method with these named parameters to run after $delay
     * seconds, and returns its job: handled (->handled, ->result),
     * waiting, or failed (->error_message). With $unique, the same job
     * (method and parameters) that is waiting or running isn't queued twice,
     * and a failed one is queued again.
     *
     * A job's own error is thrown only for 'sync' routing.
     *
     * @throws InvalidArgumentException for a bad method, or parameters that don't fit it
     */
    public function dispatch(string $method, array $parameters = [], bool $unique = false, int $delay = 0): Job {
        $job = Job::create($method, $parameters, $unique, $delay > 0 ? time() + $delay : 0);
        if ($problems = $this->problems($job)) {
            throw new InvalidArgumentException(implode(' ', $problems));
        }
        $name = $this->routing[$method] ?? array_key_first($this->queues) ?? self::SYNC;
        if ($name === self::SYNC) {
            return $job->with(queue: self::SYNC, handled: true, result: $this->handle($job));
        }
        $queue = $this->queue($name);
        $queued = $queue->enqueue($job);
        if ($delay > 0 || $this->in_request_without_worker <= 0 || !$queued->is_waiting()
            || $queue->has_live_worker($this->in_request_without_worker)) {
            return $queued;
        }
        return $this->handle_in_request($queue, $queued);
    }

    /**
     * Runs the job and returns what it returned.
     *
     * @throws Unrecoverable_job_exception when the job no longer fits its method
     * @throws Throwable whatever the job throws
     */
    public function handle(Job $job): mixed {
        if ($problems = $this->problems($job)) {
            throw new Unrecoverable_job_exception(implode(' ', $problems));
        }
        return ($this->runner)($job->method, $job->parameters);
    }

    /**
     * What doesn't fit the job's method as the code is now; [] when it fits.
     *
     * @return string[]
     */
    public function problems(Job $job): array {
        if (!preg_match(Job::METHOD_PATTERN, $job->method)) {
            return ["{$job->method} isn't a method."];
        }
        return $this->checker === null ? [] : ($this->checker)($job->method, $job->parameters);
    }

    /** @throws InvalidArgumentException for a name not in the config */
    public function queue(string $name): Job_queue {
        return $this->queues[$name] ?? throw new InvalidArgumentException("No queue named $name in config/queue.php.");
    }

    private function handle_in_request(Job_queue $queue, Job $queued): Job {
        $reserved = $queue->dequeue_id($queued->id, 'request-' . bin2hex(random_bytes(4)));
        if ($reserved === null) {
            return $queued;
        }
        ($this->log)("Queue: no worker on {$reserved->queue}, running {$reserved->label()} (#{$reserved->id}) in the request.");
        try {
            $result = $this->handle($reserved);
        } catch (Throwable $e) {
            $queue->fail($reserved, $e);
            ($this->log)("Queue: {$reserved->label()} (#{$reserved->id}) failed: " . $e->getMessage());
            return $queue->find($reserved->id) ?? $reserved;
        }
        $queue->ack($reserved);
        return $reserved->with(handled: true, result: $result, reserved_at: null, reserved_by: null);
    }
}
