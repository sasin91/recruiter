--TEST--
unknown changes are refused
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump(Company_rules::refuse_change($owner, $recruiter, 'delete_everything', 1));
?>
--EXPECT--
string(15) "Unknown change."
