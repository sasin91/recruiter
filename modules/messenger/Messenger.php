<?php
require_once __DIR__ . '/Messenger_runtime.php';

/**
 * Messenger in a Trongate app: Messenger::dispatch() from any module, and
 * messenger/manage, an admin page with the waiting and failed messages and
 * the workers, where failed messages are retried or removed.
 *
 * The app's config/messenger.php says what goes where (Messenger_runtime);
 * the messages use the 'default' database group from config/database.php.
 * Workers run bin/messenger.php (see README.md).
 */
class Messenger extends Trongate {

    private static ?Messenger_runtime $runtime = null;

    /**
     * The app's bus, transports and workers, built once per request.
     *
     * @throws RuntimeException when config/messenger.php or the database config is missing
     */
    public static function runtime(): Messenger_runtime {
        if (self::$runtime === null) {
            $path = APPPATH . 'config/messenger.php';
            if (!is_file($path)) {
                throw new RuntimeException('config/messenger.php is missing; see modules/messenger/README.md.');
            }
            self::$runtime = Messenger_runtime::from_file(self::connection(), $path);
        }
        return self::$runtime;
    }

    /**
     * Sends a message where config/messenger.php routes it, and returns its
     * envelope (handled, waiting or failed). Never a URL.
     */
    public static function dispatch(object $message, int $delay = 0): Envelope {
        return self::runtime()->bus()->dispatch($message, $delay);
    }

    /**
     * The messages with these dedupe keys, in any transport, as key =>
     * Envelope: to show "being processed" or "failed: why" next to the
     * record a message is about. Missing keys have no message (never
     * queued, or handled). Never a URL.
     *
     * @param string[] $keys
     * @return array<string, Envelope>
     */
    public static function by_dedupe_keys(array $keys): array {
        $found = [];
        foreach (self::runtime()->transports() as $transport) {
            $found += $transport->by_dedupe_keys($keys);
        }
        return $found;
    }

    /**
     * messenger/manage: counts per transport, the workers, and the waiting
     * and failed messages.
     *
     * @return void
     */
    public function manage(): void {
        $this->trongate_security->make_sure_allowed();
        $runtime = self::runtime();
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
     * POST messenger/submit_retry/{id}: queues a failed message again.
     *
     * @return void
     */
    public function submit_retry(): void {
        $this->act(fn(Database_transport $t, Envelope $e) => $t->retry_failed($e->id), 'Queued again.', "It isn't failed any more.");
    }

    /**
     * POST messenger/submit_remove/{id}: deletes a message that isn't running.
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
        $runtime = self::runtime();
        $id = (int) segment(3);
        $message = 'That message is gone: handled or removed.';
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
    public static function connection(): PDO {
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
