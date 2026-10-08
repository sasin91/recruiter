--TEST--
another user, another secret or a tampered row can't read it
--FILE--
<?php
require __DIR__ . '/setup.inc';
$vault = new Key_vault($secret);
$stored = $vault->encrypt($key, 7);
echo 'other user: ';
var_dump($vault->decrypt($stored, 8));
echo 'other secret: ';
var_dump((new Key_vault($secret . 'x'))->decrypt($stored, 7));
$raw = base64_decode($stored);
$raw[30] = chr(ord($raw[30]) ^ 1);
echo 'tampered: ';
var_dump($vault->decrypt(base64_encode($raw), 7));
echo 'garbage: ';
var_dump($vault->decrypt('not base64!', 7));
?>
--EXPECT--
other user: NULL
other secret: NULL
tampered: NULL
garbage: NULL
