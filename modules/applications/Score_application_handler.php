<?php
require_once __DIR__ . '/Score_application.php';
require_once __DIR__ . '/Applications.php';

/**
 * Scores the application (Applications::score()) and returns what went
 * wrong without stopping the score (no AI key, Laya didn't answer). An
 * exception fails this try and Messenger retries it.
 */
final class Score_application_handler {

    private ?Applications $applications = null;

    public function __invoke(Score_application $message): array {
        $this->applications ??= new Applications('applications');
        return $this->applications->score($message->application_id);
    }
}
