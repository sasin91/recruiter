--TEST--
a dedupe key queues a message once while it waits; a failed one is queued again by the next dispatch
--FILE--
<?php
require __DIR__ . '/setup.inc';
$clock = new Test_clock();
$handler = new Recording_handler();
$bus = memory_bus($clock, $handler, $transport);
$transport->worker_alive = true;

$first = $bus->dispatch(new Send_greeting(5));
$second = $bus->dispatch(new Send_greeting(5));
$other = $bus->dispatch(new Send_greeting(6));
var_dump($first->id === $second->id, $first->id !== $other->id, count($transport->messages));

$transport->fail($transport->claim('w1'), new RuntimeException('boom'));
echo $transport->find($first->id)->is_failed() ? "failed\n" : "not failed\n";
$again = $bus->dispatch(new Send_greeting(5));
var_dump($again->id === $first->id, $again->is_waiting(), $again->attempts, count($transport->messages));
echo implode(' ', array_keys($transport->by_dedupe_keys(['send_greeting:5', 'send_greeting:6', 'send_greeting:7']))), "\n";
?>
--EXPECT--
bool(true)
bool(true)
int(2)
failed
bool(true)
bool(true)
int(0)
int(2)
send_greeting:5 send_greeting:6
