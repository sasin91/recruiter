<?php
require_once __DIR__ . '/../Messenger_runtime.php';
require_once __DIR__ . '/../Messenger_console.php';
require_once __DIR__ . '/../In_memory_transport.php';

/** A clock the test moves by hand. */
final class Test_clock {
    public int $now = 1_000_000;

    public function closure(): Closure {
        return fn(): int => $this->now;
    }
}

/** A runner that records the calls it made, and throws while $failures_left > 0. */
final class Recording_runner {
    public array $calls = [];
    public int $failures_left = 0;
    public ?Throwable $error = null;

    public function closure(): Closure {
        return function (string $target, array $arguments) {
            if ($this->failures_left > 0) {
                $this->failures_left--;
                throw $this->error ?? new RuntimeException('Service unavailable');
            }
            $this->calls[] = Envelope::key($target, $arguments);
            return 'done';
        };
    }
}

function quiet(): Closure {
    return function (string $line) {
    };
}

/** A bus whose default transport is an in-memory 'async'. */
function memory_bus(Test_clock $clock, Recording_runner $runner, ?In_memory_transport &$transport = null, array $routing = []): Message_bus {
    $transport = new In_memory_transport('async', $clock->closure());
    return new Message_bus($routing, ['async' => $transport], $runner->closure(), 60, quiet());
}

/** A PDO on MESSENGER_TEST_DSN with fresh messenger tables, or null. */
function test_database(): ?PDO {
    $dsn = getenv('MESSENGER_TEST_DSN');
    if (!$dsn) {
        return null;
    }
    $db = new PDO($dsn, getenv('MESSENGER_TEST_USER') ?: 'root', getenv('MESSENGER_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('DROP TABLE IF EXISTS messenger_message_arguments, messenger_message_fields, messenger_messages, messenger_workers');
    $db->exec(file_get_contents(__DIR__ . '/../sql/messenger.sql'));
    return $db;
}
