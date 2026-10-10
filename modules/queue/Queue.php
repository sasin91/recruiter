<?php
require_once __DIR__ . '/Queue_runtime.php';
require_once __DIR__ . '/Call_signature.php';

/**
 * A job queue for a Trongate app: "run this later".
 *
 *   $this->queue->_enqueue('_score', [$id]);
 *
 * queues a job for this module's _score($id); a worker (bin/queue.php work) runs it the way a controller
 * calls another module:
 *
 *   $this->module('applications');
 *   $this->applications->_score(application_id: $id);
 *
 * retries it when it throws, and keeps it with its error when it keeps
 * failing. The method is public and starts with _, so Trongate never
 * serves it from a URL. Parameters are named, so adding an optional one
 * later doesn't break jobs already queued; they are checked against the
 * method when queued and again before they run (Call_signature).
 *
 * queue/manage is an admin page with the waiting and failed jobs and
 * the workers, where failed jobs are retried or removed. config/queue.php
 * (optional) sets queues and retries (Queue_runtime); the queue uses
 * the 'default' database group from config/database.php.
 */
class Queue extends Trongate {

    private static ?Queue_runtime $runtime = null;

    /**
     * Queues a call to run later and returns the job: ->handled (it ran in
     * this request, no worker running) with ->result, waiting, or failed
     * with ->error_message.
     *
     *   $this->queue->_enqueue('_score', [42]);                         // this module's _score(42)
     *   $this->queue->_enqueue('_score', ['application_id' => 42]);     // by name
     *   $this->queue->_enqueue('_score', [42], 'applications');         // another module's
     *
     * Without $module, the method is on the module that calls this.
     * Arguments are positional or named, as in a normal call; positional
     * ones are stored under their parameter names, so the job still runs if
     * the method's parameters are reordered later.
     *
     * @throws InvalidArgumentException for a module or method that doesn't exist, or arguments that don't fit it
     */
    public function _enqueue(string|Closure $method, array $arguments = [], ?string $module = null): Job {
        return $this->dispatch($method, $arguments, $module, false, 0);
    }

    /** _enqueue(), but the same call isn't queued twice while it waits or runs (a failed one is queued again). */
    public function _enqueue_unique(string|Closure $method, array $arguments = [], ?string $module = null): Job {
        return $this->dispatch($method, $arguments, $module, true, 0);
    }

    /** _enqueue(), run $seconds from now at the earliest (always by a worker). */
    public function _enqueue_in(int $seconds, string|Closure $method, array $arguments = [], ?string $module = null): Job {
        return $this->dispatch($method, $arguments, $module, false, $seconds);
    }

    /**
     * The unique jobs for these calls that are still around (waiting,
     * running or failed), as Job::key() => Job. A job that ran is gone.
     * Shows "being processed" or "failed: why" next to the record a job is
     * about.
     *
     *   $this->queue->_pending('_score', [[1], [2]], 'applications');
     *
     * @param array[] $argument_sets the arguments of each call, positional or named
     * @return array<string, Job>
     */
    public function _pending(string $method, array $argument_sets, ?string $module = null): array {
        $module ??= $this->calling_module($method);
        $keys = array_map(fn(array $arguments) => Job::key($module, $method, self::named($module, $method, $arguments)), $argument_sets);
        $found = [];
        foreach (self::_runtime()->queues() as $queue) {
            $found += $queue->by_unique_keys($keys);
        }
        return $found;
    }

    private function dispatch(string|Closure $method, array $arguments, ?string $module, bool $unique, int $delay): Job {
        if ($method instanceof Closure) {
            throw new InvalidArgumentException('Closures can\'t be queued yet: queue a _method on a module instead.');
        }
        $module ??= $this->calling_module($method);
        return self::_runtime()->dispatcher()->dispatch($module, $method, self::named($module, $method, $arguments), $unique, $delay);
    }

    /**
     * The module that called _enqueue(), _pending() and so on: the first
     * object up the stack that isn't this queue (or a closure).
     */
    private function calling_module(string $method): string {
        $caller = null;
        foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 6) as $frame) {
            if (isset($frame['object']) && $frame['object'] !== $this && !$frame['object'] instanceof Closure) {
                $caller = $frame['object'];
                break;
            }
        }
        if (!$caller instanceof Trongate || (string) $caller->module_name === '') {
            throw new InvalidArgumentException("Say which module $method is on: it isn't called from a module.");
        }
        return $caller->module_name;
    }

    /**
     * Arguments as named parameters: positional ones get the method's
     * parameter names, in order. Left as they are when the method can't be
     * found (Job and the signature check say why).
     */
    private static function named(string $module, string $method, array $args): array {
        if (array_is_list($args) && $args === []) {
            return [];
        }
        try {
            $parameters = self::reflect($module, $method)->getParameters();
        } catch (Unrecoverable_job_exception) {
            return $args;
        }
        $named = [];
        foreach ($args as $key => $value) {
            if (is_int($key)) {
                $parameter = $parameters[$key] ?? null;
                if ($parameter === null || $parameter->isVariadic()) {
                    throw new InvalidArgumentException("$module/$method takes " . count($parameters) . ' argument(s) by position; pass the rest by name.');
                }
                $key = $parameter->getName();
            }
            if (array_key_exists($key, $named)) {
                throw new InvalidArgumentException("$module/$method gets \$$key twice.");
            }
            $named[$key] = $value;
        }
        return $named;
    }

    /**
     * The app's dispatcher, queues and workers, built once per process
     * (bin/queue.php uses it too).
     *
     * @throws RuntimeException when the database config is missing
     */
    public static function _runtime(?Closure $log = null): Queue_runtime {
        return self::$runtime ??= Queue_runtime::from_file(
            self::_connection(),
            APPPATH . 'config/queue.php',
            fn(Job $job) => (new self('queue'))->run($job),
            $log,
            fn(Job $job) => self::signature_problems($job),
        );
    }

    /** Runs a job as a controller would, on a fresh Queue so no module is shared between jobs. */
    private function run(Job $job): mixed {
        self::reflect($job->module, $job->method);
        $module = $job->module;
        $method = $job->method;
        $this->module($module);
        return $this->$module->$method(...$job->parameters);
    }

    /**
     * What doesn't fit the job's method as the code is now: it doesn't
     * exist, or the parameters don't fit its signature.
     *
     * @return string[]
     */
    private static function signature_problems(Job $job): array {
        try {
            return Call_signature::problems(self::reflect($job->module, $job->method), $job->parameters);
        } catch (Unrecoverable_job_exception $e) {
            return [$e->getMessage()];
        }
    }

    /**
     * The job's method, from the controller $this->module() would load:
     * modules/{module}/{Module}.php, or modules/{parent}/{child}/{Child}.php
     * for 'parent-child'.
     *
     * @throws Unrecoverable_job_exception when there is no such public _method
     */
    private static function reflect(string $module, string $method): ReflectionMethod {
        $target = "$module/$method";
        if (!preg_match(Job::MODULE_PATTERN, $module)) {
            throw new Unrecoverable_job_exception("$target doesn't exist: $module isn't a module name.");
        }
        $file = APPPATH . 'modules/' . $module . '/' . ucfirst($module) . '.php';
        $class = ucfirst($module);
        if (!is_file($file) && str_contains($module, '-')) {
            [$parent, $child] = explode('-', $module, 2);
            $file = APPPATH . "modules/$parent/$child/" . ucfirst($child) . '.php';
            $class = ucfirst($child);
        }
        if (!is_file($file)) {
            throw new Unrecoverable_job_exception("$target doesn't exist: there is no module $module.");
        }
        require_once $file;
        $reflection = class_exists($class, false) && method_exists($class, $method) ? new ReflectionMethod($class, $method) : null;
        if ($reflection === null || !$reflection->isPublic() || $reflection->isStatic() || !str_starts_with($method, '_')) {
            throw new Unrecoverable_job_exception("$target doesn't exist: $class has no public method $method.");
        }
        return $reflection;
    }

    /**
     * queue/manage: counts per queue, the workers, and the waiting
     * and failed jobs.
     *
     * @return void
     */
    public function manage(): void {
        $this->trongate_security->make_sure_allowed();
        $runtime = self::_runtime();
        $queues = [];
        foreach ($runtime->queues() as $name => $queue) {
            $queues[$name] = [
                'counts' => $queue->counts(),
                'failed' => $queue->list('failed', 100),
                'waiting' => $queue->list('waiting', 100),
            ];
        }
        $workers = $runtime->registry()->list();
        $this->templates->admin([
            'view_module' => 'queue',
            'view_file' => 'manage',
            'queues' => $queues,
            'workers' => $workers,
            'live' => array_filter($workers, fn(array $w) => $w['stopped_at'] === null && (int) $w['last_seen_at'] >= time() - 60),
        ]);
    }

    /**
     * POST queue/submit_retry/{id}: queues a failed job again.
     *
     * @return void
     */
    public function submit_retry(): void {
        $this->act(fn(Database_job_queue $t, Job $e) => $t->retry_failed($e->id), 'Queued again.', "It isn't failed any more.");
    }

    /**
     * POST queue/submit_remove/{id}: deletes a job that isn't running.
     *
     * @return void
     */
    public function submit_remove(): void {
        $this->act(fn(Database_job_queue $t, Job $e) => $t->remove($e->id), 'Removed.', "It's running right now; try again when it's done.");
    }

    private function act(Closure $action, string $done, string $refused): void {
        $this->trongate_security->make_sure_allowed();
        if ($this->validation->run() !== true) {
            set_flashdata("That didn't go through. Reload the page and try again.");
            redirect('queue/manage');
            return;
        }
        $runtime = self::_runtime();
        $id = (int) segment(3);
        $message = 'That job is gone: it ran, or was removed.';
        foreach ($runtime->queues() as $queue) {
            if ($job = $queue->find($id)) {
                $message = $action($runtime->queue($job->queue), $job) ? $done : $refused;
                break;
            }
        }
        set_flashdata($message);
        redirect('queue/manage');
    }

    /** A PDO connection from config/database.php's 'default' group. */
    public static function _connection(): PDO {
        $databases = $GLOBALS['databases'] ?? [];
        $db = $databases['default'] ?? throw new RuntimeException("config/database.php has no 'default' database.");
        return new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'] ?: '3306', $db['database']),
            $db['user'],
            $db['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}
