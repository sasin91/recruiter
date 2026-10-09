--TEST--
a post moves draft → live → paused/closed → archived, and only along those moves
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump(Job_post_rules::next_status('draft', 'publish'));
var_dump(Job_post_rules::next_status('active', 'pause'));
var_dump(Job_post_rules::next_status('paused', 'resume'));
var_dump(Job_post_rules::next_status('paused', 'close'));
var_dump(Job_post_rules::next_status('closed', 'reopen'));
var_dump(Job_post_rules::next_status('closed', 'archive'));
echo 'publish a paused post: ';
var_dump(Job_post_rules::next_status('paused', 'publish'));
echo 'archive a live post: ';
var_dump(Job_post_rules::next_status('active', 'archive'));
echo 'unknown action: ';
var_dump(Job_post_rules::next_status('draft', 'delete'));
echo implode(', ', Job_post_rules::actions('active')), "\n";
echo implode(', ', Job_post_rules::actions('archived')), "|\n";
?>
--EXPECT--
string(6) "active"
string(6) "paused"
string(6) "active"
string(6) "closed"
string(6) "active"
string(8) "archived"
publish a paused post: NULL
archive a live post: NULL
unknown action: NULL
pause, close
|
