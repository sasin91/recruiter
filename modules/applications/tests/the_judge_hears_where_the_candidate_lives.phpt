--TEST--
the judge hears where the candidate lives: their postal code and its town
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump(Application_scoring::lives_in('4200'), Application_scoring::lives_in(' 0000 '), Application_scoring::lives_in(null));
?>
--EXPECT--
string(13) "4200 Slagelse"
string(16) "postal code 0000"
string(0) ""
