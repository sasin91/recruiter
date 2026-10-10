--TEST--
no worker seen: dispatch handles the message in the request, and a failure there is a failed message, not an exception; 'sync' routing throws
--FILE--
<?php
require __DIR__ . '/setup.inc';
$clock = new Test_clock();
$handler = new Recording_handler();
$bus = memory_bus($clock, $handler, $transport);

$e = $bus->dispatch(new Plain_note('now'));
var_dump($e->handled, $e->result, count($transport->messages));

$handler->failures_left = 1;
$e = $bus->dispatch(new Plain_note('fails'));
var_dump($e->handled, $e->is_failed(), $e->error_message);

$e = $bus->dispatch(new Plain_note('later'), 30);
var_dump($e->handled, $e->is_waiting());

$transport->worker_alive = true;
$e = $bus->dispatch(new Plain_note('worker'));
var_dump($e->handled, $e->is_waiting(), count($transport->messages));

$sync = new Message_bus([], [], new Handler_locator(['Plain_note' => fn() => $handler]), 60, quiet());
var_dump($sync->dispatch(new Plain_note('sync'))->result);
$handler->failures_left = 1;
try {
    $sync->dispatch(new Plain_note('sync fails'));
} catch (RuntimeException $e) {
    echo 'thrown: ', $e->getMessage(), "\n";
}
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
