--TEST--
a key comes back for its owner
--FILE--
<?php
require __DIR__ . '/setup.inc';
$vault = new Key_vault($secret);
$stored = $vault->encrypt($key, 7);
echo 'stored in the clear: ';
var_dump(str_contains($stored, 'abcdefghijklmnop'));
var_dump($vault->decrypt($stored, 7));
?>
--EXPECT--
stored in the clear: bool(false)
string(44) "sk-proj-abcdefghijklmnopqrstuvwxyz0123456789"
