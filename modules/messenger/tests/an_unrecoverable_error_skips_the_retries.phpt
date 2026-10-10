--TEST--
Unrecoverable_message_exception fails a call at once, without retries
--FILE--
<?php
require __DIR__ . '/setup.inc';
$clock = new Test_clock();
$runner = new Recording_runner();
$runner->failures_left = 1;
$runner->error = new Unrecoverable_message_exception('No such application.');
$transport = new In_memory_transport('async', $clock->closure());
$bus = new Message_bus([], ['async' => $transport], $runner->closure(), 0, quiet());
$worker = new Worker(['async' => $transport], $bus, [], null, quiet(), $clock->closure());

$sent = $bus->dispatch('Applications::_score', ['application_id' => 99]);
$worker->run(['stop_when_empty' => true, 'sleep' => 0]);
$e = $transport->find($sent->id);
echo $e->label(), ': ', $e->is_failed() ? 'failed' : 'not failed', " after {$e->attempts}: {$e->error_message}\n";
?>
--EXPECT--
Applications::_score(application_id: 99): failed after 1: No such application.
