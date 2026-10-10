<?php
/**
 * The queue's settings (modules/queue/README.md). Workers run
 * `php modules/queue/runtime.php work`; without one, queued jobs run in the
 * request as before.
 */
return [
    'queues' => [
        // Scoring calls the company's AI and Laya: retry 1 s, 2 s, 4 s apart before giving up.
        'default' => ['max_retries' => 3, 'delay' => 1, 'multiplier' => 2, 'max_delay' => 3600, 'visibility_timeout' => 3600],
    ],
    'in_request_without_worker' => 60,
];
