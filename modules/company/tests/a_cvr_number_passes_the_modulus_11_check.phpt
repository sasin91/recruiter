--TEST--
a CVR number passes the modulus 11 check
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump(Company_rules::cvr('12345674'));
echo 'typed with DK and spaces: ';
var_dump(Company_rules::cvr(' DK 1234 5674 '));
echo 'wrong check digit: ';
var_dump(Company_rules::cvr('12345678'));
echo 'seven digits: ';
var_dump(Company_rules::cvr('1234567'));
echo 'leading zero: ';
var_dump(Company_rules::cvr('02345675'));
echo 'a letter: ';
var_dump(Company_rules::cvr('1234567a'));
?>
--EXPECT--
string(8) "12345674"
typed with DK and spaces: string(8) "12345674"
wrong check digit: NULL
seven digits: NULL
leading zero: NULL
a letter: NULL
