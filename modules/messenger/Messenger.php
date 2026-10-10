<?php
require_once __DIR__ . '/Messenger_runtime.php';

/**
 * Messenger in a Trongate app: "run this later".
 *
 *   Messenger::_later('applications/_score', [$id], unique: true);
 *
 * queues a call to the applications controller's _score($id); a worker
 * (bin/messenger.php consume) makes it, retries it when it throws, and
 * keeps it with its error when it keeps failing. The target is a public
 * method whose name starts with _, which Trongate never serves from a URL.
 *
 * messenger/manage is an admin page with the waiting and failed calls and
 * the workers, where failed calls are retried or removed. config/messenger.php
 * (optional) sets transports and retries (Messenger_runtime); the queue uses
 * the 'default' database group from config/database.php.
 */
class Messenger extends Trongate {

    private static ?Messenger_runtime $runtime = null;

    /**
     * The app's bus, transports and workers, built once per process.
     * (Like every public method here but the pages, it starts with _ so no
     * URL reaches it.)
     *
     * @throws RuntimeException when the database config is missing
     */
    public static function _runtime(?Closure $log = null): Messenger_runtime {
        return self::$runtime ??= Messenger_runtime::from_file(
            self::_connection(),
            APPPATH . 'config/messenger.php',
            Closure::fromCallable([self::class, 'run_target']),
            $log
        );
    }

    /**
     * Queues $target($arguments...) to run after $delay seconds and returns
     * its envelope: ->handled (it ran in this request, no worker running)
     * with ->result, waiting, or failed with ->error_message. With $unique,
     * the same call isn't queued twice while it waits or runs.
     *
     * @throws InvalidArgumentException for a bad target or argument
     */
    public static function _later(string $target, array $arguments = [], bool $unique = false, int $delay = 0): Envelope {
        return self::_runtime()->bus()->dispatch($target, $arguments, $unique, $delay);
    }

    /**
     * The calls queued with unique that are still around (waiting, running
     * or failed), for these argument lists, as Envelope::key() => Envelope.
     * A call that ran is gone. Shows "being processed" or "failed: why" next
     * to the record a call is about.
     *
     * @param array[] $argument_lists e.g. [[1], [2]]
     * @return array<string, Envelope>
     */
    public static function _pending(string $target, array $argument_lists): array {
        $keys = array_map(fn(array $arguments) => Envelope::key($target, $arguments), $argument_lists);
        $found = [];
        foreach (self::_runtime()->transports() as $transport) {
            $found += $transport->by_dedupe_keys($keys);
        }
        return $found;
    }

    /**
     * Makes a queued call: loads the module's controller (as Modules::run
     * does) and calls the method.
     *
     * @throws Unrecoverable_message_exception when the target doesn't exist or isn't a public _method
     */
    private static function run_target(string $target, array $arguments): mixed {
        $parts = explode('/', $target);
        $method = array_pop($parts);
        $module = implode('/', $parts);
        $class = ucfirst((string) end($parts));
        $file = APPPATH . 'modules/' . $module . '/' . $class . '.php';
        if (!str_starts_with($method, '_') || !is_file($file)) {
            throw new Unrecoverable_message_exception("$target doesn't exist: no modules/$module/$class.php, or the method doesn't start with _.");
        }
        require_once $file;
        $reflection = class_exists($class) && method_exists($class, $method) ? new ReflectionMethod($class, $method) : null;
        if ($reflection === null || !$reflection->isPublic() || $reflection->isStatic()) {
            throw new Unrecoverable_message_exception("$target doesn't exist: $class has no public method $method.");
        }
        return (new $class(end($parts)))->$method(...$arguments);
    }

    /**
     * messenger/manage: counts per transport, the workers, and the waiting
     * and failed messages.
     *
     * @return void
     */
    public function manage(): void {
        $this->trongate_security->make_sure_allowed();
        $runtime = self::_runtime();
        $transports = [];
        foreach ($runtime->transports() as $name => $transport) {
            $transports[$name] = [
                'counts' => $transport->counts(),
                'failed' => $transport->list('failed', 100),
                'waiting' => $transport->list('waiting', 100),
            ];
        }
        $workers = $runtime->registry()->list();
        $this->templates->admin([
            'view_module' => 'messenger',
            'view_file' => 'manage',
            'transports' => $transports,
            'workers' => $workers,
            'live' => array_filter($workers, fn(array $w) => $w['stopped_at'] === null && (int) $w['last_seen_at'] >= time() - 60),
        ]);
    }

    /**
     * POST messenger/submit_retry/{id}: queues a failed call again.
     *
     * @return void
     */
    public function submit_retry(): void {
        $this->act(fn(Database_transport $t, Envelope $e) => $t->retry_failed($e->id), 'Queued again.', "It isn't failed any more.");
    }

    /**
     * POST messenger/submit_remove/{id}: deletes a call that isn't running.
     *
     * @return void
     */
    public function submit_remove(): void {
        $this->act(fn(Database_transport $t, Envelope $e) => $t->remove($e->id), 'Removed.', "It's running right now; try again when it's done.");
    }

    private function act(Closure $action, string $done, string $refused): void {
        $this->trongate_security->make_sure_allowed();
        if ($this->validation->run() !== true) {
            set_flashdata("That didn't go through. Reload the page and try again.");
            redirect('messenger/manage');
            return;
        }
        $runtime = self::_runtime();
        $id = (int) segment(3);
        $message = 'That call is gone: it ran, or was removed.';
        foreach ($runtime->transports() as $transport) {
            if ($envelope = $transport->find($id)) {
                $message = $action($runtime->transport($envelope->transport), $envelope) ? $done : $refused;
                break;
            }
        }
        set_flashdata($message);
        redirect('messenger/manage');
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
