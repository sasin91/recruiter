<?php
/**
 * Messenger's settings (modules/messenger/README.md). Workers run
 * `php bin/messenger.php consume`; without one, queued calls run in the
 * request as before.
 */
return [
    'transports' => [
        // Scoring calls the company's AI and Laya: retry 1 s, 2 s, 4 s apart before giving up.
        'async' => ['max_retries' => 3, 'delay' => 1, 'multiplier' => 2, 'max_delay' => 3600, 'redeliver_after' => 3600],
    ],
    'in_request_without_worker' => 60,
];
