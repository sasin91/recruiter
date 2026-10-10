--TEST--
no worker seen: dispatch runs the call in the request, and a failure there is a failed call, not an exception; 'sync' routing throws
--FILE--
<?php
require __DIR__ . '/setup.inc';
$clock = new Test_clock();
$runner = new Recording_runner();
$bus = memory_bus($clock, $runner, $transport, ['notes/_now' => 'sync']);

$e = $bus->dispatch('notes/_save', ['now']);
var_dump($e->handled, $e->result, count($transport->messages));

$runner->failures_left = 1;
$e = $bus->dispatch('notes/_save', ['fails']);
var_dump($e->handled, $e->is_failed(), $e->error_message);

$e = $bus->dispatch('notes/_save', ['later'], delay: 30);
var_dump($e->handled, $e->is_waiting());

$transport->worker_alive = true;
$e = $bus->dispatch('notes/_save', ['worker']);
var_dump($e->handled, $e->is_waiting(), count($transport->messages));

var_dump($bus->dispatch('notes/_now', [1])->result);
$runner->failures_left = 1;
try {
    $bus->dispatch('notes/_now', [2]);
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
notes/_save('now') notes/_now(1)
