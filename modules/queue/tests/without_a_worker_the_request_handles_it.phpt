--TEST--
no worker seen: dispatch runs the job in the request, and a failure there is a failed job, not an exception; 'sync' routing throws
--FILE--
<?php
require __DIR__ . '/setup.inc';
$clock = new Test_clock();
$runner = new Recording_runner();
$dispatcher = memory_bus($clock, $runner, $queue, ['notes/_now' => 'sync']);

$e = $dispatcher->dispatch('notes', '_save', ['text' => 'now']);
var_dump($e->handled, $e->result, count($queue->jobs));

$runner->failures_left = 1;
$e = $dispatcher->dispatch('notes', '_save', ['text' => 'fails']);
var_dump($e->handled, $e->is_failed(), $e->error_message);

$e = $dispatcher->dispatch('notes', '_save', ['text' => 'later'], delay: 30);
var_dump($e->handled, $e->is_waiting());

$queue->worker_alive = true;
$e = $dispatcher->dispatch('notes', '_save', ['text' => 'worker']);
var_dump($e->handled, $e->is_waiting(), count($queue->jobs));

var_dump($dispatcher->dispatch('notes', '_now', ['n' => 1])->result);
$runner->failures_left = 1;
try {
    $dispatcher->dispatch('notes', '_now', ['n' => 2]);
} catch (RuntimeException $e) {
    echo 'thrown: ', $e->getMessage(), "\n";
}
echo implode(' ', $runner->calls), "\n";
?>
--EXPECT--
bool(true)
string(4) "done"
int(0)
bool(false)
bool(true)
string(19) "Service unavailable"
bool(false)
bool(true)
bool(false)
bool(true)
int(3)
string(4) "done"
thrown: Service unavailable
notes/_save(text: "now") notes/_now(n: 1)
