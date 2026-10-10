<?php

/**
 * A queued job: the method to run ('Applications::_score'), its named
 * parameters, and its state in the queue (id, attempts, reserved, failed,
 * last error).
 *
 * Immutable; with() returns a changed copy.
 */
final class Job {

    /** Controller::_method: a public method whose name starts with _, so no URL reaches it. */
    const METHOD_PATTERN = '/^[A-Z][A-Za-z0-9_]*::_[A-Za-z0-9_]+$/';

    public function __construct(
        public readonly string $method,
        public readonly array $parameters = [],
        public readonly string $queue = '',
        public readonly ?int $id = null,
        public readonly ?string $unique_key = null,
        public readonly int $available_at = 0,
        public readonly int $attempts = 0,
        public readonly ?int $reserved_at = null,
        public readonly ?string $reserved_by = null,
        public readonly ?int $failed_at = null,
        public readonly ?string $error_class = null,
        public readonly ?string $error_message = null,
        public readonly int $created_at = 0,
        public readonly bool $handled = false,
        public readonly mixed $result = null,
    ) {
    }

    /**
     * A new job, checked: a valid method, and parameters passed by name
     * whose values are int, float, bool, string, null or arrays of those
     * (they are stored as JSON).
     *
     * @throws InvalidArgumentException
     */
    public static function create(string $method, array $parameters = [], bool $unique = false, int $available_at = 0): self {
        if (!preg_match(self::METHOD_PATTERN, $method)) {
            throw new InvalidArgumentException("$method isn't a job method: use Controller::_method, a public method whose name starts with _.");
        }
        foreach ($parameters as $name => $value) {
            if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException("Parameters of $method are passed by name, e.g. ['application_id' => 42].");
            }
            self::check_value($method, $name, $value);
        }
        return new self(
            method: $method,
            parameters: $parameters,
            unique_key: $unique ? self::key($method, $parameters) : null,
            available_at: $available_at,
        );
    }

    /**
     * The unique key of a job queued with unique: the method and its
     * parameters, e.g. 'Applications::_score(application_id: 42)'. Order
     * of the parameters doesn't matter; a long one is hashed to fit 191
     * characters.
     */
    public static function key(string $method, array $parameters = []): string {
        ksort($parameters);
        $key = $method . '(' . implode(', ', array_map(
            fn($name, $value) => "$name: " . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            array_keys($parameters),
            $parameters
        )) . ')';
        return mb_strlen($key) <= 191 ? $key : mb_substr($method, 0, 140) . '#' . sha1($key);
    }

    /** The job as a person reads it: Applications::_score(application_id: 42). */
    public function label(): string {
        $label = self::key($this->method, $this->parameters);
        return str_contains($label, '#') ? $this->method . '(…)' : $label;
    }

    /** The parameters as stored in queue_jobs.parameters. */
    public function parameters_json(): string {
        return json_encode((object) $this->parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /** Parameters back from their JSON. */
    public static function parameters_from_json(string $json): array {
        return (array) json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    }

    /** Waiting for a worker (or for its retry delay). */
    public function is_waiting(): bool {
        return !$this->handled && $this->failed_at === null && $this->reserved_at === null;
    }

    /** Reserved by a worker and not finished. */
    public function is_running(): bool {
        return !$this->handled && $this->failed_at === null && $this->reserved_at !== null;
    }

    /** Retries used up (or not retryable); stays until retried or removed. */
    public function is_failed(): bool {
        return $this->failed_at !== null;
    }

    /** An error as stored with a job: its text, cut to 1,000 characters. */
    public static function error_text(Throwable $error): string {
        $text = trim($error->getMessage()) !== '' ? $error->getMessage() : get_class($error);
        return mb_strlen($text) > 1000 ? mb_substr($text, 0, 999) . '…' : $text;
    }

    /** A copy with these properties changed. */
    public function with(mixed ...$changes): self {
        $values = get_object_vars($this);
        foreach ($changes as $name => $value) {
            if (!array_key_exists($name, $values)) {
                throw new InvalidArgumentException("Job has no property $name.");
            }
            $values[$name] = $value;
        }
        return new self(...$values);
    }

    private static function check_value(string $method, string $name, mixed $value): void {
        if (is_array($value)) {
            foreach ($value as $item) {
                self::check_value($method, $name, $item);
            }
        } elseif (!is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException("Parameter $name of $method is " . get_debug_type($value) . '; a job takes int, float, bool, string, null or arrays of those.');
        } elseif (is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException("Parameter $name of $method isn't a finite number.");
        }
    }
}
