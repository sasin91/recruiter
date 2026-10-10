--TEST--
Unrecoverable_message_exception and a message nothing handles fail at once
--FILE--
<?php
require __DIR__ . '/setup.inc';
$clock = new Test_clock();
$handler = new Recording_handler();
$handler->failures_left = 1;
$handler->error = new Unrecoverable_message_exception('No such application.');
$transport = new In_memory_transport('async', $clock->closure());
$bus = new Message_bus(['Plain_note' => 'async', 'Send_greeting' => 'async'], ['async' => $transport],
    new Handler_locator(['Plain_note' => fn() => $handler]), 0, quiet());
$worker = new Worker(['async' => $transport], $bus, [], null, quiet(), $clock->closure());

$a = $bus->dispatch(new Plain_note('x'));
$b = $bus->dispatch(new Send_greeting(1));
$worker->run(['stop_when_empty' => true, 'sleep' => 0]);
foreach ([$a, $b] as $sent) {
    $e = $transport->find($sent->id);
    echo $e->message_class(), ': ', $e->is_failed() ? 'failed' : 'not failed', " after {$e->attempts}: {$e->error_message}\n";
}
?>
--EXPECT--
Plain_note: failed after 1: No such application.
Send_greeting: failed after 1: No handler for Send_greeting in config/messenger.php.
