--TEST--
Database_job_queue: enqueue with named parameters (JSON) and a unique key, hand each job out once, retry, fail, requeue a dead worker's job after the visibility timeout, worker heartbeat
--SKIPIF--
<?php if (!getenv('QUEUE_TEST_DSN')) echo 'skip QUEUE_TEST_DSN not set (a MariaDB/MySQL database the test may drop tables in)'; ?>
--FILE--
<?php
require __DIR__ . '/setup.inc';
$db = test_database();
$clock = new Test_clock();
$async = new Database_job_queue($db, 'default', 3600, $clock->closure());
$other = new Database_job_queue($db, 'emails', 3600, $clock->closure());

$parameters = ['person_id' => 1, 'greeting' => 'hej "dig" æøå', 'weight' => 1.0, 'tags' => ['a', 'b'], 'loud' => true, 'note' => null];
$a = $async->enqueue(Job::create('People::_greet', $parameters, unique: true));
$dup = $async->enqueue(Job::create('People::_greet', $parameters, unique: true));
$b = $async->enqueue(Job::create('Notes::_save', ['text' => 'later'], available_at: $clock->now + 10));
$c = $other->enqueue(Job::create('Mail::_send', ['mail_id' => 3]));
var_dump($dup->id === $a->id, $async->counts(), $other->counts());

$reserved = $async->dequeue('worker-one-00001');
var_dump($reserved->id === $a->id, $reserved->parameters === $parameters, $reserved->reserved_by);
var_dump($async->dequeue('worker-two-00002'), $async->dequeue_id($a->id, 'request-1'));

$async->retry($reserved, new RuntimeException('try again'), $clock->now + 5);
$clock->now += 10;
$first = $async->dequeue('worker-two-00002');
$second = $async->dequeue('worker-one-00001');
echo "after 10s: #", $first->id === $a->id ? 'a' : 'b', ' then #', $second->id === $a->id ? 'a' : 'b', ", attempts {$first->attempts}, error {$first->error_message}\n";

// Worker one dies holding b; an hour later it's queued again.
$clock->now += 3601;
$async->fail($first, new LogicException(str_repeat('x', 1200)));
$again = $async->dequeue('worker-three-003');
var_dump($again->id === $b->id, strlen($async->find($a->id)->error_message), $async->find($a->id)->is_failed());
$async->ack($again);
var_dump($async->find($b->id), $db->query('SELECT parameters FROM queue_jobs WHERE id = ' . $a->id)->fetchColumn());

var_dump(array_keys($async->by_unique_keys([Job::key('People::_greet', $parameters), 'nope'])), $async->remove($a->id), $async->counts());

$registry = new Worker_registry($db, $clock->closure());
var_dump($other->has_live_worker(60));
$registry->start('worker-four-0004', ['default', 'emails']);
var_dump($other->has_live_worker(60), $async->has_live_worker(60));
$clock->now += 61;
var_dump($other->has_live_worker(60));
$registry->beat('worker-four-0004', 3, 1);
var_dump($other->has_live_worker(60));
$registry->stop('worker-four-0004', 3, 1);
var_dump($other->has_live_worker(60), (int) $registry->list()[0]['handled']);
?>
--EXPECTF--
bool(true)
array(3) {
  ["waiting"]=>
  int(2)
  ["running"]=>
  int(0)
  ["failed"]=>
  int(0)
}
array(3) {
  ["waiting"]=>
  int(1)
  ["running"]=>
  int(0)
  ["failed"]=>
  int(0)
}
bool(true)
bool(true)
string(16) "worker-one-00001"
NULL
NULL
after 10s: #a then #b, attempts 1, error try again
bool(true)
int(1002)
bool(true)
NULL
string(101) "{"person_id":1,"greeting":"hej \"dig\" æøå","weight":1.0,"tags":["a","b"],"loud":true,"note":null}"
array(1) {
  [0]=>
  string(%d) "People::_greet(%s)"
}
bool(true)
array(3) {
  ["waiting"]=>
  int(0)
  ["running"]=>
  int(0)
  ["failed"]=>
  int(0)
}
bool(false)
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
int(3)
