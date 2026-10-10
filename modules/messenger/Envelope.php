<?php

/**
 * A queued call: the target ('Applications::_score'), its named parameters,
 * and what the transport knows about it (Messenger's envelope and stamps,
 * as plain properties).
 *
 * Immutable; with() returns a changed copy.
 */
final class Envelope {

    /** Controller::_method: a public method whose name starts with _, so no URL reaches it. */
    const TARGET_PATTERN = '/^[A-Z][A-Za-z0-9_]*::_[A-Za-z0-9_]+$/';

    public function __construct(
        public readonly string $target,
        public readonly array $parameters = [],
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

    /**
     * A new call, checked: a valid target, and parameters passed by name
     * whose values are int, float, bool, string, null or arrays of those
     * (they are stored as JSON).
     *
     * @throws InvalidArgumentException
     */
    public static function call(string $target, array $parameters = [], bool $unique = false, int $available_at = 0): self {
        if (!preg_match(self::TARGET_PATTERN, $target)) {
            throw new InvalidArgumentException("$target isn't a target: use Controller::_method, a public method whose name starts with _.");
        }
        foreach ($parameters as $name => $value) {
            if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException("Parameters of $target are passed by name, e.g. ['application_id' => 42].");
            }
            self::check_value($target, $name, $value);
        }
        return new self(
            target: $target,
            parameters: $parameters,
            dedupe_key: $unique ? self::key($target, $parameters) : null,
            available_at: $available_at,
        );
    }

    /**
     * The dedupe key of a call queued with unique: the target and its
     * parameters, e.g. 'Applications::_score(application_id: 42)'. Order
     * of the parameters doesn't matter; a long one is hashed to fit 191
     * characters.
     */
    public static function key(string $target, array $parameters = []): string {
        ksort($parameters);
        $key = $target . '(' . implode(', ', array_map(
            fn($name, $value) => "$name: " . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            array_keys($parameters),
            $parameters
        )) . ')';
        return mb_strlen($key) <= 191 ? $key : mb_substr($target, 0, 140) . '#' . sha1($key);
    }

    /** The call as a person reads it: Applications::_score(application_id: 42). */
    public function label(): string {
        $label = self::key($this->target, $this->parameters);
        return str_contains($label, '#') ? $this->target . '(…)' : $label;
    }

    /** The parameters as stored in messenger_messages.parameters. */
    public function parameters_json(): string {
        return json_encode((object) $this->parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /** Parameters back from their JSON. */
    public static function parameters_from_json(string $json): array {
        return (array) json_decode($json, true, 64, JSON_THROW_ON_ERROR);
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

    /** An error as stored with a call: its text, cut to 1,000 characters. */
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

    private static function check_value(string $target, string $name, mixed $value): void {
        if (is_array($value)) {
            foreach ($value as $item) {
                self::check_value($target, $name, $item);
            }
        } elseif (!is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException("Parameter $name of $target is " . get_debug_type($value) . '; a call takes int, float, bool, string, null or arrays of those.');
        } elseif (is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException("Parameter $name of $target isn't a finite number.");
        }
    }
}
