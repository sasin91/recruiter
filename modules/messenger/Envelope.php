<?php
require_once __DIR__ . '/Unreadable_message.php';

/**
 * A message on its way: the message itself plus what the transport knows
 * about it (Messenger's envelope and stamps, as plain properties).
 *
 * Immutable; the with_*() methods return a changed copy.
 */
final class Envelope {

    public function __construct(
        public readonly object $message,
        public readonly string $transport = '',
        public readonly ?int $id = null,
        public readonly ?string $dedupe_key = null,
        public readonly int $available_at = 0,
        public readonly int $attempts = 0,
        public readonly ?int $delivered_at = null,
        public readonly ?string $delivered_to = null,
        public readonly ?int $failed_at = null,
        public readonly ?string $error_class = null,
        public readonly ?string $error_message = null,
        public readonly int $created_at = 0,
        public readonly bool $handled = false,
        public readonly mixed $result = null,
    ) {
    }

    /** The message's class, as stored. */
    public function message_class(): string {
        return $this->message instanceof Unreadable_message ? $this->message->stored_class : get_class($this->message);
    }

    /** Waiting for a worker (or for its retry delay). */
    public function is_waiting(): bool {
        return !$this->handled && $this->failed_at === null && $this->delivered_at === null;
    }

    /** Claimed by a worker and not finished. */
    public function is_running(): bool {
        return !$this->handled && $this->failed_at === null && $this->delivered_at !== null;
    }

    /** Retries used up (or not retryable); stays until retried or removed. */
    public function is_failed(): bool {
        return $this->failed_at !== null;
    }

    /** An error as stored with a message: its text, cut to 1,000 characters. */
    public static function error_text(Throwable $error): string {
        $text = trim($error->getMessage()) !== '' ? $error->getMessage() : get_class($error);
        return mb_strlen($text) > 1000 ? mb_substr($text, 0, 999) . '…' : $text;
    }

    /** A copy with these properties changed. */
    public function with(mixed ...$changes): self {
        $values = get_object_vars($this);
        foreach ($changes as $name => $value) {
            if (!array_key_exists($name, $values)) {
                throw new InvalidArgumentException("Envelope has no property $name.");
            }
            $values[$name] = $value;
        }
        return new self(...$values);
    }
}
