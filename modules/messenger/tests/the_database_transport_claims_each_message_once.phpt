--TEST--
Database_transport: send with fields and dedupe, claim once, retry, fail, redeliver a dead worker's message, worker heartbeat
--SKIPIF--
<?php if (!getenv('MESSENGER_TEST_DSN')) echo 'skip MESSENGER_TEST_DSN not set (a MariaDB/MySQL database the test may drop tables in)'; ?>
--FILE--
<?php
require __DIR__ . '/setup.inc';
$db = test_database();
$clock = new Test_clock();
$async = new Database_transport($db, 'async', 3600, $clock->closure());
$other = new Database_transport($db, 'emails', 3600, $clock->closure());

$a = $async->send(new Envelope(new Send_greeting(1, 'hej', 1.25, true), dedupe_key: 'send_greeting:1'));
$dup = $async->send(new Envelope(new Send_greeting(1), dedupe_key: 'send_greeting:1'));
$b = $async->send(new Envelope(new Plain_note('later'), available_at: $clock->now + 10));
$c = $other->send(new Envelope(new Plain_note('mail')));
var_dump($dup->id === $a->id, $async->counts(), $other->counts());

$claimed = $async->claim('worker-one-00001');
var_dump($claimed->id === $a->id, $claimed->message == new Send_greeting(1, 'hej', 1.25, true), $claimed->delivered_to);
var_dump($async->claim('worker-two-00002'), $async->claim_id($a->id, 'request-1'));

$async->retry($claimed, new RuntimeException('try again'), $clock->now + 5);
$clock->now += 10;
$first = $async->claim('worker-two-00002');
$second = $async->claim('worker-one-00001');
echo "after 10s: #", $first->id === $a->id ? 'a' : 'b', ' then #', $second->id === $a->id ? 'a' : 'b', ", attempts {$first->attempts}, error {$first->error_message}\n";

// Worker one dies holding b; an hour later it's queued again.
$clock->now += 3601;
$async->fail($first, new LogicException(str_repeat('x', 1200)));
$again = $async->claim('worker-three-003');
var_dump($again->id === $b->id, strlen($async->find($a->id)->error_message), $async->find($a->id)->is_failed());
$async->ack($again);
var_dump($async->find($b->id), (int) $db->query('SELECT COUNT(*) FROM messenger_message_fields WHERE messenger_message_id = ' . $b->id)->fetchColumn());

var_dump(array_keys($async->by_dedupe_keys(['send_greeting:1', 'nope'])), $async->remove($a->id), $async->counts());

$registry = new Worker_registry($db, $clock->closure());
var_dump($other->has_live_worker(60));
$registry->start('worker-four-0004', ['async', 'emails']);
var_dump($other->has_live_worker(60), $async->has_live_worker(60));
$clock->now += 61;
var_dump($other->has_live_worker(60));
$registry->beat('worker-four-0004', 3, 1);
var_dump($other->has_live_worker(60));
$registry->stop('worker-four-0004', 3, 1);
var_dump($other->has_live_worker(60), (int) $registry->list()[0]['handled']);
?>
--EXPECT--
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
int(0)
array(1) {
  [0]=>
  string(15) "send_greeting:1"
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
