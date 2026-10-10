--TEST--
a call that throws is retried after 1, 2 and 4 seconds, then fails and keeps the error; retrying it queues it again
--FILE--
<?php
require __DIR__ . '/setup.inc';
$clock = new Test_clock();
$runner = new Recording_runner();
$runner->failures_left = 10;
$bus = memory_bus($clock, $runner, $transport);
$transport->worker_alive = true;
$worker = new Worker(['async' => $transport], $bus, ['async' => new Retry_strategy()], null, quiet(), $clock->closure());

$sent = $bus->dispatch('Notes::_save', ['text' => 'x']);
for ($i = 0; $i < 6; $i++) {
    $worker->run(['stop_when_empty' => true, 'sleep' => 0]);
    $e = $transport->find($sent->id);
    echo "t+", $clock->now - 1_000_000, ': ', $e->is_failed() ? 'failed' : 'waiting until t+' . ($e->available_at - 1_000_000), ", attempts {$e->attempts}\n";
    $clock->now = $e->available_at;
}
echo $e->error_class, ': ', $e->error_message, "\n";
echo "handled {$worker->handled}, failed {$worker->failed}\n";

$runner->failures_left = 0;
var_dump($transport->retry_failed($sent->id), $transport->retry_failed($sent->id));
$worker->run(['stop_when_empty' => true, 'sleep' => 0]);
var_dump($transport->find($sent->id), $runner->calls);
?>
--EXPECT--
t+0: waiting until t+1, attempts 1
t+1: waiting until t+3, attempts 2
t+3: waiting until t+7, attempts 3
t+7: failed, attempts 4
t+7: failed, attempts 4
t+7: failed, attempts 4
RuntimeException: Service unavailable
handled 0, failed 1
bool(true)
bool(false)
NULL
array(1) {
  [0]=>
  string(23) "Notes::_save(text: "x")"
}
