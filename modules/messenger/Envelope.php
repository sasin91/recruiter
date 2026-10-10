<?php

/**
 * A queued call: the target ('module/_method'), its arguments, and what the
 * transport knows about it (Messenger's envelope and stamps, as plain
 * properties).
 *
 * Immutable; with() returns a changed copy.
 */
final class Envelope {

    /** module/_method, or module/child/_method: a public method whose name starts with _. */
    const TARGET_PATTERN = '#^[a-z][a-z0-9_]*(/[a-z][a-z0-9_]*)?/_[A-Za-z0-9_]+$#';

    /** The argument types a call can carry: they are stored as text and come back the same. */
    const ARGUMENT_TYPES = ['int', 'float', 'bool', 'string', 'null'];

    public function __construct(
        public readonly string $target,
        public readonly array $arguments = [],
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
     * A new call, checked: a valid target, and arguments that are a list of
     * int, float, bool, string or null.
     *
     * @throws InvalidArgumentException
     */
    public static function call(string $target, array $arguments = [], ?string $dedupe_key = null, int $available_at = 0): self {
        if (!preg_match(self::TARGET_PATTERN, $target)) {
            throw new InvalidArgumentException("$target isn't a target: use module/_method, a public method whose name starts with _.");
        }
        if (!array_is_list($arguments)) {
            throw new InvalidArgumentException('Arguments are passed in order: a list, not named.');
        }
        foreach ($arguments as $i => $argument) {
            if (!is_scalar($argument) && $argument !== null) {
                throw new InvalidArgumentException("Argument $i of $target is " . get_debug_type($argument) . '; a call takes only int, float, bool, string or null.');
            }
        }
        if ($dedupe_key !== null && mb_strlen($dedupe_key) > 191) {
            throw new InvalidArgumentException('A dedupe key is at most 191 characters.');
        }
        return new self(target: $target, arguments: $arguments, dedupe_key: $dedupe_key, available_at: $available_at);
    }

    /**
     * The dedupe key of a call queued with unique: the target and its
     * arguments, e.g. 'applications/_score(42)'.
     */
    public static function key(string $target, array $arguments = []): string {
        return $target . '(' . implode(',', array_map(fn($a) => var_export($a, true), $arguments)) . ')';
    }

    /** The call as a person reads it: applications/_score(42). */
    public function label(): string {
        return self::key($this->target, $this->arguments);
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

    /** An argument as stored: [type, text or null]. */
    public static function store_argument(mixed $value): array {
        return match (true) {
            is_int($value) => ['int', (string) $value],
            is_float($value) => ['float', var_export($value, true)],
            is_bool($value) => ['bool', $value ? '1' : '0'],
            is_string($value) => ['string', $value],
            default => ['null', null],
        };
    }

    /** An argument back from [type, text or null]. */
    public static function load_argument(string $type, ?string $value): mixed {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => $value === '1',
            'string' => (string) $value,
            default => null,
        };
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
}
