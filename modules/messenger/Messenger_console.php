<?php
require_once __DIR__ . '/Messenger_runtime.php';

/**
 * The command line (bin/messenger.php), after Symfony's messenger:* commands:
 *
 *   consume [transport ...] [--limit=N] [--time-limit=SECONDS] [--memory-limit=128M] [--sleep=1] [--stop-when-empty]
 *   check
 *   stats
 *   failed:show [id] [--transport=NAME] [--limit=50]
 *   failed:retry id ... | --all [--transport=NAME]
 *   failed:remove id ... [--transport=NAME]
 *
 * Without --transport the failed:* commands look in every transport.
 */
final class Messenger_console {

    const USAGE = <<<'TEXT'
    Usage: php bin/messenger.php <command>

      consume [transport ...]   Run queued calls until stopped (all transports, most urgent first)
          --limit=N               stop after N calls
          --time-limit=SECONDS    stop after this long (let the process manager start it again)
          --memory-limit=128M     stop when memory use passes this
          --sleep=1               seconds to wait when nothing is due
          --stop-when-empty       stop as soon as nothing is due
      check                     Waiting and failed calls that no longer fit their method (consume runs this first)
      stats                     Waiting, running and failed calls per transport, and the workers
      failed:show [id]          Failed calls, or one call in full
      failed:retry id ...       Queue failed calls again (--all for every one)
      failed:remove id ...      Delete failed calls
          --transport=NAME        only this transport (failed:*)
          --limit=50              how many to show (failed:show)

    TEXT;

    /** @param resource $out */
    public function __construct(private readonly Messenger_runtime $runtime, private $out = STDOUT) {
    }

    /** Runs argv (without the script name) and returns the exit code. */
    public function run(array $args): int {
        [$command, $positional, $options] = self::parse($args);
        try {
            return match ($command) {
                'consume' => $this->consume($positional, $options),
                'check' => $this->check() === 0 ? 0 : 1,
                'stats' => $this->stats(),
                'failed:show' => $this->failed_show($positional, $options),
                'failed:retry' => $this->failed_retry($positional, $options),
                'failed:remove' => $this->failed_remove($positional, $options),
                default => $this->usage($command),
            };
        } catch (InvalidArgumentException $e) {
            $this->say($e->getMessage());
            return 2;
        }
    }

    /** [command, positional args, --options as name => value (true for flags)] */
    public static function parse(array $args): array {
        $command = '';
        $positional = [];
        $options = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                [$name, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
                $options[$name] = $value;
            } elseif ($command === '') {
                $command = $arg;
            } else {
                $positional[] = $arg;
            }
        }
        return [$command, $positional, $options];
    }

    /** '128M' => bytes; plain numbers are bytes. */
    public static function bytes(string $size): int {
        $number = (float) $size;
        return (int) match (strtoupper(substr(trim($size), -1))) {
            'K' => $number * 1024,
            'M' => $number * 1024 ** 2,
            'G' => $number * 1024 ** 3,
            default => $number,
        };
    }

    private function consume(array $names, array $options): int {
        $this->check();
        $worker = $this->runtime->worker($names);
        $worker->run([
            'limit' => (int) ($options['limit'] ?? 0),
            'time_limit' => (int) ($options['time-limit'] ?? 0),
            'memory_limit' => isset($options['memory-limit']) ? self::bytes((string) $options['memory-limit']) : 0,
            'sleep' => (float) ($options['sleep'] ?? 1),
            'stop_when_empty' => isset($options['stop-when-empty']),
        ]);
        $this->say("Handled {$worker->handled}, failed {$worker->failed}.");
        return 0;
    }

    /**
     * Checks each waiting and failed call against the code as it is now, so a
     * changed method signature shows up when a worker starts, not when the call runs.
     * Returns how many calls no longer fit.
     */
    private function check(): int {
        $bad = 0;
        foreach ($this->runtime->transports() as $transport) {
            foreach (['waiting', 'failed'] as $state) {
                foreach ($transport->list($state, 1000) as $e) {
                    if ($problems = $this->runtime->bus()->problems($e)) {
                        $bad++;
                        $this->say("#{$e->id} {$e->label()} ($state) no longer fits: " . implode(' ', $problems));
                    }
                }
            }
        }
        if ($bad > 0) {
            $this->say("$bad queued call" . ($bad === 1 ? '' : 's') . " won't run as queued. Change the method back, or remove them (failed:remove) once they fail.");
        }
        return $bad;
    }

    private function stats(): int {
        foreach ($this->runtime->transports() as $name => $transport) {
            $c = $transport->counts();
            $this->say(sprintf('%-12s %5d waiting %5d running %5d failed', $name, $c['waiting'], $c['running'], $c['failed']));
        }
        $workers = array_filter($this->runtime->registry()->list(), fn(array $w) => $w['stopped_at'] === null);
        if ($workers === []) {
            $this->say('No worker running.');
        }
        foreach ($workers as $w) {
            $this->say(sprintf(
                'Worker %s on %s (pid %d): %s, %d handled, %d failed, last seen %ds ago',
                $w['id'], $w['hostname'], $w['process_id'], $w['transports'], $w['handled'], $w['failed'], time() - (int) $w['last_seen_at']
            ));
        }
        return 0;
    }

    private function failed_show(array $ids, array $options): int {
        if ($ids) {
            foreach ($ids as $id) {
                $envelope = $this->find((int) $id, $options);
                if ($envelope === null) {
                    $this->say("No message $id.");
                    continue;
                }
                $this->describe($envelope);
            }
            return 0;
        }
        $limit = (int) ($options['limit'] ?? 50);
        $any = false;
        foreach ($this->transports($options) as $transport) {
            foreach ($transport->list('failed', $limit) as $e) {
                $any = true;
                $this->say(sprintf(
                    '#%d %s %s, failed %s after %d tries: %s',
                    $e->id, $e->transport, $e->label(), date('Y-m-d H:i', (int) $e->failed_at), $e->attempts, $e->error_message
                ));
            }
        }
        if (!$any) {
            $this->say('No failed messages.');
        }
        return 0;
    }

    private function failed_retry(array $ids, array $options): int {
        $retried = 0;
        foreach ($this->transports($options) as $transport) {
            if (isset($options['all'])) {
                do {
                    $batch = $transport->list('failed', 500);
                    foreach ($batch as $e) {
                        $retried += $transport->retry_failed($e->id) ? 1 : 0;
                    }
                } while (count($batch) === 500);
                continue;
            }
            foreach ($ids as $id) {
                $retried += $transport->retry_failed((int) $id) ? 1 : 0;
            }
        }
        $this->say("Queued $retried message" . ($retried === 1 ? '' : 's') . ' again.');
        return 0;
    }

    private function failed_remove(array $ids, array $options): int {
        $removed = 0;
        foreach ($ids as $id) {
            $envelope = $this->find((int) $id, $options);
            if ($envelope !== null && $envelope->is_failed()) {
                $removed += $this->runtime->transport($envelope->transport)->remove($envelope->id) ? 1 : 0;
            }
        }
        $this->say("Removed $removed failed message" . ($removed === 1 ? '' : 's') . '.');
        return 0;
    }

    private function describe(Envelope $e): void {
        $state = $e->is_failed() ? 'failed ' . date('Y-m-d H:i:s', (int) $e->failed_at)
            : ($e->is_running() ? "running on worker {$e->delivered_to}" : 'waiting until ' . date('Y-m-d H:i:s', $e->available_at));
        $this->say("#{$e->id} {$e->label()} on {$e->transport}: $state, tried {$e->attempts} times" . ($e->dedupe_key !== null ? ', unique' : ''));
        if ($e->error_message !== null) {
            $this->say("  last error: {$e->error_class}: {$e->error_message}");
        }
    }

    private function find(int $id, array $options): ?Envelope {
        foreach ($this->transports($options) as $transport) {
            if ($envelope = $transport->find($id)) {
                return $envelope;
            }
        }
        return null;
    }

    /** @return Database_transport[] */
    private function transports(array $options): array {
        return isset($options['transport']) && $options['transport'] !== true
            ? [$this->runtime->transport((string) $options['transport'])]
            : $this->runtime->transports();
    }

    private function usage(string $command): int {
        if ($command !== '' && $command !== 'help') {
            $this->say("Unknown command $command.\n");
        }
        fwrite($this->out, self::USAGE);
        return $command === '' || $command === 'help' ? 0 : 2;
    }

    private function say(string $line): void {
        fwrite($this->out, $line . "\n");
    }
}
