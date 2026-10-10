--TEST--
a call is module/_method with a list of int, float, bool, string or null; anything else is refused when queued
--FILE--
<?php
require __DIR__ . '/setup.inc';
$e = Envelope::call('applications/_score', [42, "Hej\nmed dig", 0.5, true, null]);
echo $e->label(), "\n";
foreach ($e->arguments as $argument) {
    var_dump(Envelope::load_argument(...Envelope::store_argument($argument)) === $argument);
}
echo Envelope::call('company/members/_invite', [1])->target, "\n";
foreach ([['applications/score', []], ['applications/_score', [[1]]], ['applications/_score', ['id' => 1]], ['Applications/_score', []], ['../x/_y', []]] as [$target, $arguments]) {
    try {
        Envelope::call($target, $arguments);
        echo "accepted $target\n";
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
applications/_score(42,'Hej
med dig',0.5,true,NULL)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
company/members/_invite
applications/score isn't a target: use module/_method, a public method whose name starts with _.
Argument 0 of applications/_score is array; a call takes only int, float, bool, string or null.
Arguments are passed in order: a list, not named.
Applications/_score isn't a target: use module/_method, a public method whose name starts with _.
../x/_y isn't a target: use module/_method, a public method whose name starts with _.
