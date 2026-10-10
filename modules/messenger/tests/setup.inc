<?php
require_once __DIR__ . '/../Messenger_runtime.php';
require_once __DIR__ . '/../Messenger_console.php';
require_once __DIR__ . '/../In_memory_transport.php';

final class Send_greeting implements Deduplicated_message {
    public function __construct(public readonly int $person_id, public readonly string $text = 'hi', public readonly ?float $weight = null, public readonly bool $loud = false) {
    }

    public function dedupe_key(): string {
        return "send_greeting:{$this->person_id}";
    }
}

final class Plain_note {
    public function __construct(public readonly string $text) {
    }
}

/** A clock the test moves by hand. */
final class Test_clock {
    public int $now = 1_000_000;

    public function closure(): Closure {
        return fn(): int => $this->now;
    }
}

/** Handlers that record what they handled, and fail while $failures_left > 0. */
final class Recording_handler {
    public array $handled = [];
    public int $failures_left = 0;
    public ?Throwable $error = null;

    public function __invoke(object $message): string {
        if ($this->failures_left > 0) {
            $this->failures_left--;
            throw $this->error ?? new RuntimeException('Service unavailable');
        }
        $this->handled[] = get_class($message) . ':' . json_encode(get_object_vars($message));
        return 'done';
    }
}

function quiet(): Closure {
    return function (string $line) {
    };
}

/** A bus on one in-memory 'async' transport routing both test messages there. */
function memory_bus(Test_clock $clock, Recording_handler $handler, In_memory_transport &$transport = null, int $in_request = 60): Message_bus {
    $transport = new In_memory_transport('async', $clock->closure());
    return new Message_bus(
        ['Send_greeting' => 'async', 'Plain_note' => 'async'],
        ['async' => $transport],
        new Handler_locator(['Send_greeting' => fn() => $handler, 'Plain_note' => fn() => $handler]),
        $in_request,
        quiet(),
    );
}

/** A PDO on MESSENGER_TEST_DSN with fresh messenger tables, or null. */
function test_database(): ?PDO {
    $dsn = getenv('MESSENGER_TEST_DSN');
    if (!$dsn) {
        return null;
    }
    $db = new PDO($dsn, getenv('MESSENGER_TEST_USER') ?: 'root', getenv('MESSENGER_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('DROP TABLE IF EXISTS messenger_message_fields, messenger_messages, messenger_workers');
    $db->exec(file_get_contents(__DIR__ . '/../sql/messenger.sql'));
    return $db;
}
