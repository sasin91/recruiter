--TEST--
each encryption is different
--FILE--
<?php
require __DIR__ . '/setup.inc';
$vault = new Key_vault($secret);
var_dump($vault->encrypt($key, 7) === $vault->encrypt($key, 7));
?>
--EXPECT--
bool(false)
