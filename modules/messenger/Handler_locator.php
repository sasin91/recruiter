<?php
require_once __DIR__ . '/Unrecoverable_message_exception.php';

/**
 * Which handler handles a message: message class => a factory returning
 * the handler (any callable taking the message, usually an object with
 * __invoke). Each handler is built once, when first needed.
 */
final class Handler_locator {

    private array $handlers = [];

    /** @param array<string, callable(): callable> $factories */
    public function __construct(private readonly array $factories) {
    }

    /** @throws Unrecoverable_message_exception when nothing handles this class */
    public function handler_for(string $class): callable {
        if (!isset($this->handlers[$class])) {
            if (!isset($this->factories[$class])) {
                throw new Unrecoverable_message_exception("No handler for $class in config/messenger.php.");
            }
            $this->handlers[$class] = ($this->factories[$class])();
        }
        return $this->handlers[$class];
    }

    /** @return string[] the message classes with a handler */
    public function classes(): array {
        return array_keys($this->factories);
    }
}
