<?php
require_once __DIR__ . '/Envelope.php';
require_once __DIR__ . '/Transport.php';
require_once __DIR__ . '/Handler_locator.php';
require_once __DIR__ . '/Deduplicated_message.php';
require_once __DIR__ . '/Unreadable_message.php';

/**
 * Sends messages where the routing says: a transport name (a worker handles
 * it later), or 'sync' (handled now, in this request). A class the routing
 * doesn't name is handled now, as in Symfony Messenger.
 *
 * When no worker has been seen on the transport for $in_request_without_worker
 * seconds, a message due now is handled in the request after all, so the app
 * keeps working while its worker is down or not deployed yet. If it fails
 * there, it is marked failed at once (nobody would run the retries) and
 * dispatch() returns the failed envelope; it doesn't throw.
 */
final class Message_bus {

    const SYNC = 'sync';

    private Closure $log;

    /**
     * @param array<string, string> $routing message class => transport name or 'sync'
     * @param array<string, Transport> $transports
     */
    public function __construct(
        private readonly array $routing,
        private readonly array $transports,
        private readonly Handler_locator $handlers,
        private readonly int $in_request_without_worker = 60,
        ?Closure $log = null,
    ) {
        $this->log = $log ?? fn(string $line) => error_log($line);
    }

    /**
     * Sends a message (after $delay seconds) and returns its envelope:
     * handled (->handled, ->result), waiting, or failed (->error_message).
     *
     * A handler error is thrown only for 'sync' routing.
     */
    public function dispatch(object $message, int $delay = 0): Envelope {
        $class = get_class($message);
        $name = $this->routing[$class] ?? self::SYNC;
        $envelope = new Envelope(
            message: $message,
            dedupe_key: $message instanceof Deduplicated_message ? $message->dedupe_key() : null,
            available_at: $delay > 0 ? time() + $delay : 0,
        );
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
     * Runs the message's handler and returns what it returned.
     *
     * @throws Throwable whatever the handler throws
     */
    public function handle(Envelope $envelope): mixed {
        $message = $envelope->message;
        if ($message instanceof Unreadable_message) {
            throw new Unrecoverable_message_exception($message->reason);
        }
        return ($this->handlers->handler_for(get_class($message)))($message);
    }

    /** @throws InvalidArgumentException for a name not in config/messenger.php */
    public function transport(string $name): Transport {
        return $this->transports[$name] ?? throw new InvalidArgumentException("No transport named $name in config/messenger.php.");
    }

    private function handle_in_request(Transport $transport, Envelope $sent): Envelope {
        $claimed = $transport->claim_id($sent->id, 'request-' . bin2hex(random_bytes(4)));
        if ($claimed === null) {
            return $sent;
        }
        ($this->log)("Messenger: no worker on {$claimed->transport}, handling {$claimed->message_class()} #{$claimed->id} in the request.");
        try {
            $result = $this->handle($claimed);
        } catch (Throwable $e) {
            $transport->fail($claimed, $e);
            ($this->log)("Messenger: {$claimed->message_class()} #{$claimed->id} failed: " . $e->getMessage());
            return $transport->find($claimed->id) ?? $claimed;
        }
        $transport->ack($claimed);
        return $claimed->with(handled: true, result: $result, delivered_at: null, delivered_to: null);
    }
}
