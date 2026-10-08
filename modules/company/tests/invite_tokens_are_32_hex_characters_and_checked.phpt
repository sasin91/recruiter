--TEST--
invite tokens are 32 hex characters and checked before lookup
--FILE--
<?php
require __DIR__ . '/setup.inc';
$token = Company_rules::invite_token();
var_dump(Company_rules::is_invite_token($token));
echo 'two tokens alike: ';
var_dump($token === Company_rules::invite_token());
var_dump(Company_rules::is_invite_token("' OR 1=1 --"));
var_dump(Company_rules::is_invite_token(strtoupper($token)));
?>
--EXPECT--
bool(true)
two tokens alike: bool(false)
bool(false)
bool(false)
