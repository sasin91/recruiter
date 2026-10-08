--TEST--
only owners change members, and never themselves
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump(Company_rules::refuse_change($recruiter, $owner, 'deactivate', 1));
var_dump(Company_rules::refuse_change($owner, $owner, 'make_recruiter', 2));
var_dump(Company_rules::refuse_change($owner, $recruiter, 'make_owner', 1));
var_dump(Company_rules::refuse_change($owner, $recruiter, 'deactivate', 1));
?>
--EXPECT--
string(33) "Only an owner can change members."
string(52) "You can't change your own access. Ask another owner."
NULL
NULL
