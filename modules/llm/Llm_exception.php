<?php
/**
 * A failed model request, with a message fit to show the user. `retryable`
 * is true for rate limits and overload, where trying again later can work.
 */
class Llm_exception extends RuntimeException {

    public function __construct(string $message, public readonly bool $retryable = false, ?Throwable $previous = null) {
        parent::__construct($message, 0, $previous);
    }

}
