<?php
require_once __DIR__ . '/Database_job_queue.php';
require_once __DIR__ . '/Dispatcher.php';
require_once __DIR__ . '/Retry_policy.php';
require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/Worker_registry.php';

/**
 * The dispatcher, queues and workers an app's config describes, on one PDO
 * connection, with $runner running the jobs. Framework-free: a Trongate app
 * gets it from Queue::_runtime(); a plain PHP worker builds it with its
 * own runner.
 *
 * Config (config/queue.php returns it; every key is optional):
 *
 *   return [
 *       // Database queues, the default first, with their retry settings
 *       'queues' => [
 *           'default' => ['max_retries' => 3, 'delay' => 1, 'multiplier' => 2, 'max_delay' => 3600, 'visibility_timeout' => 3600],
 *       ],
 *       // 'module/method' => queue name, or 'sync' to run it at once (the rest use the default)
 *       'routing' => ['mail/_send' => 'emails'],
 *       // No worker seen for this many seconds: run due jobs in the request (0 = never)
 *       'in_request_without_worker' => 60,
 *   ];
 */
final class Queue_runtime {

    /** @var array<string, Database_job_queue> */
    private array $queues = [];
    /** @var array<string, Retry_policy> */
    private array $retry_policies = [];
    private Dispatcher $dispatcher;
    private Worker_registry $registry;

    /**
     * @param Closure(Job): mixed $runner makes a job
     * @param (Closure(Job): string[])|null $checker what doesn't fit a job's method (Dispatcher)
     */
    public function __construct(PDO $db, private readonly array $config, Closure $runner, private readonly ?Closure $log = null, ?Closure $checker = null) {
        foreach ($config['queues'] ?? ['default' => []] as $name => $settings) {
            $this->queues[$name] = new Database_job_queue($db, $name, (int) ($settings['visibility_timeout'] ?? 3600));
            $this->retry_policies[$name] = Retry_policy::from_config($settings);
        }
        $this->dispatcher = new Dispatcher(
            $config['routing'] ?? [],
            $this->queues,
            $runner,
            (int) ($config['in_request_without_worker'] ?? 60),
            $log,
            $checker,
        );
        $this->registry = new Worker_registry($db);
    }

    /** Loads a config file that returns the array above (no file: the defaults). */
    public static function from_file(PDO $db, string $path, Closure $runner, ?Closure $log = null, ?Closure $checker = null): self {
        $config = is_file($path) ? require $path : [];
        if (!is_array($config)) {
            throw new RuntimeException("$path must return the queue config array.");
        }
        return new self($db, $config, $runner, $log, $checker);
    }

    public function dispatcher(): Dispatcher {
        return $this->dispatcher;
    }

    /** @return array<string, Database_job_queue> */
    public function queues(): array {
        return $this->queues;
    }

    /** @throws InvalidArgumentException for an unknown name */
    public function queue(string $name): Database_job_queue {
        return $this->queues[$name] ?? throw new InvalidArgumentException("No queue named $name in config/queue.php.");
    }

    public function registry(): Worker_registry {
        return $this->registry;
    }

    /**
     * A worker on these queues (all, most urgent first, when empty).
     *
     * @param string[] $names
     */
    public function worker(array $names = []): Worker {
        $queues = [];
        foreach ($names ?: array_keys($this->queues) as $name) {
            $queues[$name] = $this->queue($name);
        }
        return new Worker($queues, $this->dispatcher, $this->retry_policies, $this->registry, $this->log);
    }
}
