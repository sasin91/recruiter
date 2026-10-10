--TEST--
unique: the same job is queued once while it waits; a failed one is queued again by the next dispatch
--FILE--
<?php
require __DIR__ . '/setup.inc';
$clock = new Test_clock();
$dispatcher = memory_bus($clock, new Recording_runner(), $queue);
$queue->worker_alive = true;

$first = $dispatcher->dispatch('Applications::_score', ['application_id' => 5], unique: true);
$second = $dispatcher->dispatch('Applications::_score', ['application_id' => 5], unique: true);
$other = $dispatcher->dispatch('Applications::_score', ['application_id' => 6], unique: true);
$not_unique = $dispatcher->dispatch('Applications::_score', ['application_id' => 5]);
var_dump($first->id === $second->id, $first->id !== $other->id, $not_unique->id !== $first->id, count($queue->jobs));

$queue->fail($queue->dequeue('w1'), new RuntimeException('boom'));
echo $queue->find($first->id)->is_failed() ? "failed\n" : "not failed\n";
$again = $dispatcher->dispatch('Applications::_score', ['application_id' => 5], unique: true);
var_dump($again->id === $first->id, $again->is_waiting(), $again->attempts);
echo implode(' ', array_keys($queue->by_unique_keys([Job::key('Applications::_score', ['application_id' => 5]), Job::key('Applications::_score', ['application_id' => 6]), Job::key('Applications::_score', ['application_id' => 7])]))), "\n";
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
