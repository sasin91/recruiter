--TEST--
Unrecoverable_job_exception fails a job at once, without retries
--FILE--
<?php
require __DIR__ . '/setup.inc';
$clock = new Test_clock();
$runner = new Recording_runner();
$runner->failures_left = 1;
$runner->error = new Unrecoverable_job_exception('No such application.');
$queue = new In_memory_job_queue('default', $clock->closure());
$dispatcher = new Dispatcher([], ['default' => $queue], $runner->closure(), 0, quiet());
$worker = new Worker(['default' => $queue], $dispatcher, [], null, quiet(), $clock->closure());

$queued = $dispatcher->dispatch('applications', '_score', ['application_id' => 99]);
$worker->run(['stop_when_empty' => true, 'sleep' => 0]);
$e = $queue->find($queued->id);
echo $e->label(), ': ', $e->is_failed() ? 'failed' : 'not failed', " after {$e->attempts}: {$e->error_message}\n";
?>
--EXPECT--
applications/_score(application_id: 99): failed after 1: No such application.
