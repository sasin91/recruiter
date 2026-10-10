--TEST--
unique: the same call is queued once while it waits; a failed one is queued again by the next dispatch
--FILE--
<?php
require __DIR__ . '/setup.inc';
$clock = new Test_clock();
$bus = memory_bus($clock, new Recording_runner(), $transport);
$transport->worker_alive = true;

$first = $bus->dispatch('Applications::_score', ['application_id' => 5], unique: true);
$second = $bus->dispatch('Applications::_score', ['application_id' => 5], unique: true);
$other = $bus->dispatch('Applications::_score', ['application_id' => 6], unique: true);
$not_unique = $bus->dispatch('Applications::_score', ['application_id' => 5]);
var_dump($first->id === $second->id, $first->id !== $other->id, $not_unique->id !== $first->id, count($transport->messages));

$transport->fail($transport->claim('w1'), new RuntimeException('boom'));
echo $transport->find($first->id)->is_failed() ? "failed\n" : "not failed\n";
$again = $bus->dispatch('Applications::_score', ['application_id' => 5], unique: true);
var_dump($again->id === $first->id, $again->is_waiting(), $again->attempts);
echo implode(' ', array_keys($transport->by_dedupe_keys([Envelope::key('Applications::_score', ['application_id' => 5]), Envelope::key('Applications::_score', ['application_id' => 6]), Envelope::key('Applications::_score', ['application_id' => 7])]))), "\n";
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
int(3)
failed
bool(true)
bool(true)
int(0)
Applications::_score(application_id: 5) Applications::_score(application_id: 6)
