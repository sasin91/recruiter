--TEST--
a company's key doesn't open as the user with the same id, or the other way round
--FILE--
<?php
require __DIR__ . '/setup.inc';
$vault = new Key_vault($secret);
$company = $vault->encrypt($key, 7, 'company');
var_dump($vault->decrypt($company, 7, 'company'));
echo 'as user 7: ';
var_dump($vault->decrypt($company, 7));
echo 'user key as company 7: ';
var_dump($vault->decrypt($vault->encrypt($key, 7), 7, 'company'));
?>
--EXPECT--
string(44) "sk-proj-abcdefghijklmnopqrstuvwxyz0123456789"
as user 7: NULL
user key as company 7: NULL
