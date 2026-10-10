<?php

/**
 * A message that should be queued once: while one with the same key is
 * waiting or running, dispatching another does nothing. A failed one with
 * the same key is queued again instead (a retry).
 */
interface Deduplicated_message {

    /** Unique per piece of work, e.g. 'score_application:42'. At most 191 characters. */
    public function dedupe_key(): string;
}
