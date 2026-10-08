--TEST--
a company keeps at least one active owner
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump(Company_rules::refuse_change($owner, $other_owner, 'deactivate', 1));
var_dump(Company_rules::refuse_change($owner, $other_owner, 'make_recruiter', 1));
echo 'with two owners: ';
var_dump(Company_rules::refuse_change($owner, $other_owner, 'deactivate', 2));
$inactive_owner = ['id' => 2, 'role' => 'owner', 'active' => 0];
echo 'an inactive owner: ';
var_dump(Company_rules::refuse_change($owner, $inactive_owner, 'make_recruiter', 1));
?>
--EXPECT--
string(35) "A company needs at least one owner."
string(35) "A company needs at least one owner."
with two owners: NULL
an inactive owner: NULL
