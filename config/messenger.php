<?php
/**
 * What Messenger sends where (modules/messenger/README.md). Workers run
 * `php bin/messenger.php consume`; without one, messages are handled in
 * the request as before.
 */
require_once APPPATH . 'modules/applications/Score_application.php';

return [
    'transports' => [
        // Scoring calls the company's AI and Laya: retry 1 s, 2 s, 4 s apart before giving up.
        'async' => ['max_retries' => 3, 'delay' => 1, 'multiplier' => 2, 'max_delay' => 3600, 'redeliver_after' => 3600],
    ],
    'routing' => [
        'Score_application' => 'async',
    ],
    'handlers' => [
        'Score_application' => function () {
            require_once APPPATH . 'modules/applications/Score_application_handler.php';
            return new Score_application_handler();
        },
    ],
    'in_request_without_worker' => 60,
];
