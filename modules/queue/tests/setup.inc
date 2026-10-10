<?php
require_once __DIR__ . '/../Queue_runtime.php';
require_once __DIR__ . '/../Queue_console.php';
require_once __DIR__ . '/../In_memory_job_queue.php';
require_once __DIR__ . '/../Call_signature.php';

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
        return function (Job $job) {
            if ($this->failures_left > 0) {
                $this->failures_left--;
                throw $this->error ?? new RuntimeException('Service unavailable');
            }
            $this->calls[] = $job->label();
            return 'done';
        };
    }
}

function quiet(): Closure {
    return function (string $line) {
    };
}

/** A dispatcher whose default queue is an in-memory 'default'. */
function memory_bus(Test_clock $clock, Recording_runner $runner, ?In_memory_job_queue &$queue = null, array $routing = [], ?Closure $checker = null): Dispatcher {
    $queue = new In_memory_job_queue('default', $clock->closure());
    return new Dispatcher($routing, ['default' => $queue], $runner->closure(), 60, quiet(), $checker);
}

/** A PDO on QUEUE_TEST_DSN with fresh queue tables, or null. */
function test_database(): ?PDO {
    $dsn = getenv('QUEUE_TEST_DSN');
    if (!$dsn) {
        return null;
    }
    $db = new PDO($dsn, getenv('QUEUE_TEST_USER') ?: 'root', getenv('QUEUE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('DROP TABLE IF EXISTS queue_jobs, queue_workers');
    $db->exec(file_get_contents(__DIR__ . '/../sql/queue.sql'));
    return $db;
}

/** A controller the signature tests check jobs against. */
final class Notes {
    public function _save(string $text, int $times = 1): string {
        return str_repeat($text, $times);
    }

    public function _tag(int|string $id, ?array $tags, float $weight = 1.0): void {
    }
}

/** Call_signature on the Notes test controller, as Queue checks real ones. */
function notes_checker(): Closure {
    return fn(Job $job) => Call_signature::problems(new ReflectionMethod(ucfirst($job->module), $job->method), $job->parameters);
}
