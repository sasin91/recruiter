<?php
require_once __DIR__ . '/Llm_response.php';

/**
 * A failed model request, with a message fit to show the user. `retryable`
 * is true for rate limits and overload, where trying again later can work.
 * `response` is the provider's HTTP answer when there was one.
 */
class Llm_exception extends RuntimeException {

    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
        public readonly ?Llm_response $response = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** The message plus the provider's response, for the server log. */
    public function details(): string {
        return $this->response === null
            ? $this->getMessage()
            : $this->getMessage() . ' (' . $this->response->summary() . ')';
    }

}
