<?php
/**
 * Stands in for a stored message that can't be rebuilt (its class is gone
 * or its fields no longer fit), so it can still be listed and removed.
 */
final class Unreadable_message {

    public function __construct(
        public readonly string $stored_class,
        public readonly string $reason,
    ) {
    }
}
