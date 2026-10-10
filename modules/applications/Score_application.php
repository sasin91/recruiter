<?php
require_once __DIR__ . '/../messenger/Deduplicated_message.php';

/**
 * Score this application against its post as the post is now
 * (Applications::score()). Queued on Send and on Re-score; one at a time
 * per application.
 */
final class Score_application implements Deduplicated_message {

    public function __construct(public readonly int $application_id) {
    }

    public function dedupe_key(): string {
        return self::key($this->application_id);
    }

    /** The dedupe key for an application, to look its message up. */
    public static function key(int $application_id): string {
        return "score_application:$application_id";
    }
}
