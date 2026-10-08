--TEST--
an English query finds the Danish term
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump($top('Project manager')['term']['id']);
var_dump($top('accounting', 'skill')['term']['id']);
?>
--EXPECT--
int(2)
int(4)
