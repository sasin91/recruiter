<?php
require_once __DIR__ . '/Database_transport.php';
require_once __DIR__ . '/Message_bus.php';
require_once __DIR__ . '/Retry_strategy.php';
require_once __DIR__ . '/Worker.php';
require_once __DIR__ . '/Worker_registry.php';

/**
 * The bus, transports and workers an app's config describes, on one PDO
 * connection. Framework-free: a Trongate app gets it from Messenger::runtime(),
 * a plain PHP worker builds it from its own config.
 *
 * Config (config/messenger.php returns it):
 *
 *   return [
 *       // Database transports, most urgent first, with their retry settings
 *       'transports' => [
 *           'async' => ['max_retries' => 3, 'delay' => 1, 'multiplier' => 2, 'max_delay' => 3600, 'redeliver_after' => 3600],
 *       ],
 *       // Message class => transport name, or 'sync' to handle it at once
 *       'routing' => ['Score_application' => 'async'],
 *       // Message class => factory returning its handler
 *       'handlers' => ['Score_application' => fn() => new Score_application_handler()],
 *       // No worker seen for this many seconds: handle due messages in the request (0 = never)
 *       'in_request_without_worker' => 60,
 *   ];
 */
final class Messenger_runtime {

    /** @var array<string, Database_transport> */
    private array $transports = [];
    /** @var array<string, Retry_strategy> */
    private array $retry_strategies = [];
    private Message_bus $bus;
    private Worker_registry $registry;

    public function __construct(PDO $db, private readonly array $config, private readonly ?Closure $log = null) {
        foreach ($config['transports'] ?? ['async' => []] as $name => $settings) {
            $this->transports[$name] = new Database_transport($db, $name, (int) ($settings['redeliver_after'] ?? 3600));
            $this->retry_strategies[$name] = Retry_strategy::from_config($settings);
        }
        $this->bus = new Message_bus(
            $config['routing'] ?? [],
            $this->transports,
            new Handler_locator($config['handlers'] ?? []),
            (int) ($config['in_request_without_worker'] ?? 60),
            $log,
        );
        $this->registry = new Worker_registry($db);
    }

    /** Loads a config file that returns the array above. */
    public static function from_file(PDO $db, string $path, ?Closure $log = null): self {
        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException("$path must return the messenger config array.");
        }
        return new self($db, $config, $log);
    }

    public function bus(): Message_bus {
        return $this->bus;
    }

    /** @return array<string, Database_transport> */
    public function transports(): array {
        return $this->transports;
    }

    /** @throws InvalidArgumentException for an unknown name */
    public function transport(string $name): Database_transport {
        return $this->transports[$name] ?? throw new InvalidArgumentException("No transport named $name in the messenger config.");
    }

    public function registry(): Worker_registry {
        return $this->registry;
    }

    /**
     * A worker on these transports (all, most urgent first, when empty).
     *
     * @param string[] $names
     */
    public function worker(array $names = []): Worker {
        $transports = [];
        foreach ($names ?: array_keys($this->transports) as $name) {
            $transports[$name] = $this->transport($name);
        }
        return new Worker($transports, $this->bus, $this->retry_strategies, $this->registry, $this->log);
    }
}
