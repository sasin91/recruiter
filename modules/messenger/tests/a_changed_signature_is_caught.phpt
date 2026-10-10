--TEST--
parameters are checked against the method: refused when queued, and a call queued under an older signature fails at once with the reason
--FILE--
<?php
require __DIR__ . '/setup.inc';
foreach ([
    ['text' => 'hi'],
    ['text' => 'hi', 'times' => '2'],
    ['times' => 2],
    ['text' => 'hi', 'count' => 2],
    ['text' => 'hi', 'times' => 2.5],
] as $parameters) {
    $problems = Call_signature::problems(new ReflectionMethod('Notes', '_save'), $parameters);
    echo $problems ? implode(' ', $problems) : 'fits', "\n";
}
foreach ([['id' => 'a-1', 'tags' => null], ['id' => 7, 'tags' => ['x'], 'weight' => 2], ['id' => true, 'tags' => []]] as $parameters) {
    $problems = Call_signature::problems(new ReflectionMethod('Notes', '_tag'), $parameters);
    echo $problems ? implode(' ', $problems) : 'fits', "\n";
}

$clock = new Test_clock();
$runner = new Recording_runner();
$bus = memory_bus($clock, $runner, $transport, [], notes_checker());
$transport->worker_alive = true;
try {
    $bus->dispatch('Notes::_save', ['txt' => 'hi']);
} catch (InvalidArgumentException $e) {
    echo 'refused: ', $e->getMessage(), "\n";
}

// Queued by the old code as Notes::_save(note: ...), then the new code renamed it.
$old = $transport->send(Envelope::call('Notes::_save', ['note' => 'hi']));
echo implode(' ', $bus->problems($old)), "\n";
$worker = new Worker(['async' => $transport], $bus, ['async' => new Retry_strategy()], null, quiet(), $clock->closure());
$worker->run(['stop_when_empty' => true, 'sleep' => 0]);
$e = $transport->find($old->id);
echo $e->is_failed() ? 'failed' : 'not failed', " after {$e->attempts}: {$e->error_class}: {$e->error_message}\n";
var_dump($runner->calls);
?>
--EXPECT--
fits
Notes::_save's $times is int, not string.
Notes::_save needs $text, which the call doesn't give.
Notes::_save has no parameter $count.
Notes::_save's $times is int, not float.
fits
fits
Notes::_tag's $id is string|int, not bool.
refused: Notes::_save has no parameter $txt. Notes::_save needs $text, which the call doesn't give.
Notes::_save has no parameter $note. Notes::_save needs $text, which the call doesn't give.
failed after 1: Unrecoverable_message_exception: Notes::_save has no parameter $note. Notes::_save needs $text, which the call doesn't give.
array(0) {
}
