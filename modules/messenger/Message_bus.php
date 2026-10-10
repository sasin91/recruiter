<?php
require_once __DIR__ . '/Envelope.php';
require_once __DIR__ . '/Transport.php';
require_once __DIR__ . '/Unrecoverable_message_exception.php';

/**
 * Queues calls ("run applications/_score(42) later") on the transport the
 * routing names for the target, or on the default transport. 'sync' runs
 * the call at once, in this request.
 *
 * $runner makes the call: (string $target, array $arguments) => result. In
 * a Trongate app it loads the module's controller and calls the method
 * (Messenger::run_target()); a framework-free app passes its own.
 *
 * When no worker has been seen on the transport for $in_request_without_worker
 * seconds, a call due now is run in the request after all, so the app keeps
 * working while its worker is down or not deployed yet. If it fails there,
 * it is marked failed at once (nobody would run the retries) and dispatch()
 * returns the failed envelope; it doesn't throw.
 */
final class Message_bus {

    const SYNC = 'sync';

    private Closure $log;

    /**
     * @param array<string, string> $routing target => transport name or 'sync'
     * @param array<string, Transport> $transports the first is the default
     * @param Closure(string, array): mixed $runner
     */
    public function __construct(
        private readonly array $routing,
        private readonly array $transports,
        private readonly Closure $runner,
        private readonly int $in_request_without_worker = 60,
        ?Closure $log = null,
    ) {
        $this->log = $log ?? fn(string $line) => error_log($line);
    }

    /**
     * Queues $target($arguments...) to run after $delay seconds, and returns
     * its envelope: handled (->handled, ->result), waiting, or failed
     * (->error_message). With $unique, a call with the same target and
     * arguments that is waiting or running isn't queued twice, and a failed
     * one is queued again.
     *
     * A call's own error is thrown only for 'sync' routing.
     *
     * @throws InvalidArgumentException for a bad target or argument
     */
    public function dispatch(string $target, array $arguments = [], bool $unique = false, int $delay = 0): Envelope {
        $envelope = Envelope::call(
            $target,
            $arguments,
            $unique ? Envelope::key($target, $arguments) : null,
            $delay > 0 ? time() + $delay : 0,
        );
        $name = $this->routing[$target] ?? array_key_first($this->transports) ?? self::SYNC;
        if ($name === self::SYNC) {
            return $envelope->with(transport: self::SYNC, handled: true, result: $this->handle($envelope));
        }
        $transport = $this->transport($name);
        $sent = $transport->send($envelope);
        if ($delay > 0 || $this->in_request_without_worker <= 0 || !$sent->is_waiting()
            || $transport->has_live_worker($this->in_request_without_worker)) {
            return $sent;
        }
        return $this->handle_in_request($transport, $sent);
    }

    /**
     * Makes the call and returns what it returned.
     *
     * @throws Throwable whatever the call throws
     */
    public function handle(Envelope $envelope): mixed {
        if (!preg_match(Envelope::TARGET_PATTERN, $envelope->target)) {
            throw new Unrecoverable_message_exception("{$envelope->target} isn't a target.");
        }
        return ($this->runner)($envelope->target, $envelope->arguments);
    }

    /** @throws InvalidArgumentException for a name not in the config */
    public function transport(string $name): Transport {
        return $this->transports[$name] ?? throw new InvalidArgumentException("No transport named $name in config/messenger.php.");
    }

    private function handle_in_request(Transport $transport, Envelope $sent): Envelope {
        $claimed = $transport->claim_id($sent->id, 'request-' . bin2hex(random_bytes(4)));
        if ($claimed === null) {
            return $sent;
        }
        ($this->log)("Messenger: no worker on {$claimed->transport}, running {$claimed->label()} (#{$claimed->id}) in the request.");
        try {
            $result = $this->handle($claimed);
        } catch (Throwable $e) {
            $transport->fail($claimed, $e);
            ($this->log)("Messenger: {$claimed->label()} (#{$claimed->id}) failed: " . $e->getMessage());
            return $transport->find($claimed->id) ?? $claimed;
        }
        $transport->ack($claimed);
        return $claimed->with(handled: true, result: $result, delivered_at: null, delivered_to: null);
    }
}
