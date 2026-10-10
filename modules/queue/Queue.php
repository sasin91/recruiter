<?php
require_once __DIR__ . '/Queue_runtime.php';
require_once __DIR__ . '/Call_signature.php';

/**
 * Queue in a Trongate app: "run this later".
 *
 *   Queue::_enqueue('Applications::_score', ['application_id' => $id], unique: true);
 *
 * queues a job to the Applications controller's _score(application_id: $id);
 * a worker (bin/queue.php work) makes it, retries it when it throws,
 * and keeps it with its error when it keeps failing. The method is a public
 * method whose name starts with _, which Trongate never serves from a URL.
 * Parameters are named, so adding an optional one later doesn't break
 * jobs already queued; they are checked against the method when queued and
 * again before they run (Call_signature).
 *
 * queue/manage is an admin page with the waiting and failed jobs and
 * the workers, where failed jobs are retried or removed. config/queue.php
 * (optional) sets queues and retries (Queue_runtime); the queue uses
 * the 'default' database group from config/database.php.
 */
class Queue extends Trongate {

    private static ?Queue_runtime $runtime = null;

    /**
     * The app's dispatcher, queues and workers, built once per process.
     * (Like every public method here but the pages, it starts with _ so no
     * URL reaches it.)
     *
     * @throws RuntimeException when the database config is missing
     */
    public static function _runtime(?Closure $log = null): Queue_runtime {
        return self::$runtime ??= Queue_runtime::from_file(
            self::_connection(),
            APPPATH . 'config/queue.php',
            Closure::fromCallable([self::class, 'run_job']),
            $log,
            Closure::fromCallable([self::class, 'signature_problems']),
        );
    }

    /**
     * Queues the job to run after $delay seconds and returns its job:
     * ->handled (it ran in this request, no worker running) with ->result,
     * waiting, or failed with ->error_message. With $unique, the same job
     * isn't queued twice while it waits or runs.
     *
     * @throws InvalidArgumentException for a method that doesn't exist, or parameters that don't fit it
     */
    public static function _enqueue(string $method, array $parameters = [], bool $unique = false, int $delay = 0): Job {
        return self::_runtime()->dispatcher()->dispatch($method, $parameters, $unique, $delay);
    }

    /**
     * The jobs queued with unique that are still around (waiting, running
     * or failed), for these parameter sets, as Job::key() => Job.
     * A job that ran is gone. Shows "being processed" or "failed: why" next
     * to the record a job is about.
     *
     * @param array[] $parameter_sets e.g. [['application_id' => 1], ['application_id' => 2]]
     * @return array<string, Job>
     */
    public static function _pending(string $method, array $parameter_sets): array {
        $keys = array_map(fn(array $parameters) => Job::key($method, $parameters), $parameter_sets);
        $found = [];
        foreach (self::_runtime()->queues() as $queue) {
            $found += $queue->by_unique_keys($keys);
        }
        return $found;
    }

    /** Makes a queued job: the controller, as Modules::run builds it, and the method with named parameters. */
    private static function run_job(string $method, array $parameters): mixed {
        [$class, $name] = explode('::', $method, 2);
        self::reflect($method);
        return (new $class(strtolower($class)))->$name(...$parameters);
    }

    /**
     * What doesn't fit the method as the code is now: it doesn't exist, or
     * the parameters don't fit its signature.
     *
     * @return string[]
     */
    private static function signature_problems(string $method, array $parameters): array {
        try {
            return Call_signature::problems(self::reflect($method), $parameters);
        } catch (Unrecoverable_job_exception $e) {
            return [$e->getMessage()];
        }
    }

    /**
     * The method's method, loading its controller from modules/{class in lower case}/.
     *
     * @throws Unrecoverable_job_exception when there is no such public _method
     */
    private static function reflect(string $method): ReflectionMethod {
        [$class, $name] = explode('::', $method, 2) + [1 => ''];
        $file = APPPATH . 'modules/' . strtolower($class) . '/' . $class . '.php';
        if (!str_starts_with($name, '_') || !is_file($file)) {
            throw new Unrecoverable_job_exception("$method doesn't exist: there is no modules/" . strtolower($class) . "/$class.php, or the method doesn't start with _.");
        }
        require_once $file;
        $reflection = class_exists($class, false) && method_exists($class, $name) ? new ReflectionMethod($class, $name) : null;
        if ($reflection === null || !$reflection->isPublic() || $reflection->isStatic()) {
            throw new Unrecoverable_job_exception("$method doesn't exist: $class has no public method $name.");
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
