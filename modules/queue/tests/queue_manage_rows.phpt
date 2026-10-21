--TEST--
queue/manage rows: a failed job can be retried or removed, a waiting one removed, a running one neither
--FILE--
<?php
require_once __DIR__ . '/../Queue_worker_row.php';
$t = mktime(9, 0, 0, 10, 20, 2026);
$rows = array_map([Queue_job_row::class, 'from'], [
    new Job('applications', '_score', ['application_id' => 4], id: 1, available_at: $t, attempts: 3, failed_at: $t, error_class: 'RuntimeException', error_message: 'Boom'),
    new Job('applications', '_score', ['application_id' => 5], id: 2, available_at: $t),
    new Job('job_scan', '_scan_due', id: 3, attempts: 1, reserved_at: $t, error_message: 'Timeout'),
]);
foreach ($rows as $r) {
    echo "#{$r['id']} {$r['label']} · {$r['state']} · failed {$r['failed']} · {$r['error_class']} {$r['error']} · ", implode(', ', array_map(fn($b) => "{$b['label']} {$b['url']}", $r['buttons'])), "\n";
}
$w = ['id' => 'w1', 'hostname' => 'pod', 'process_id' => '12', 'queues' => 'default', 'handled' => '5', 'failed' => '1', 'started_at' => (string) $t, 'last_seen_at' => (string) time(), 'stopped_at' => null];
echo implode(' | ', Queue_worker_row::from($w)), "\n";
echo Queue_worker_row::from(['last_seen_at' => (string) $t] + $w)['stopped'], ', ', Queue_worker_row::from(['last_seen_at' => (string) $t, 'stopped_at' => (string) $t] + $w)['stopped'], "\n";
?>
--EXPECTF--
#1 applications/_score(application_id: 4) · Due 20 Oct 09:00:00 · failed 20 Oct 09:00:00 · RuntimeException Boom · Retry queue/submit_retry/1, Remove queue/submit_remove/1
#2 applications/_score(application_id: 5) · Due 20 Oct 09:00:00 · failed  ·   · Remove queue/submit_remove/2
#3 job_scan/_scan_due() · Running since 20 Oct 09:00:00 · failed  ·  Timeout · 
w1 | pod (12) | default | 5 | 1 | 20 Oct 09:00:00 | %s | running
stopped answering, 20 Oct 09:00:00
