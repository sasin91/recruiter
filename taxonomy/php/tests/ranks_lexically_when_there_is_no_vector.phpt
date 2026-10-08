--TEST--
ranks lexically when there is no vector
--FILE--
<?php
require __DIR__ . '/setup.inc';
$r = $tiny->rank('regnskab', null, null, 1)[0];
var_dump($r['term']['id']);
var_dump($r['semantic']);
?>
--EXPECT--
int(4)
NULL
