--TEST--
publishing needs a title and at least one scored requirement; links are 12 letters and digits
--FILE--
<?php
require __DIR__ . '/setup.inc';
$soft_only = [['kind' => 'soft_skill'], ['kind' => 'responsibility_area']];
var_dump(Job_post_rules::refuse_publish('', [['kind' => 'skill']]));
var_dump(Job_post_rules::refuse_publish('Kok', $soft_only));
var_dump(Job_post_rules::refuse_publish('Kok', [['kind' => 'certificate']]));
$token = Job_post_rules::public_token();
var_dump(Job_post_rules::is_public_token($token));
var_dump(Job_post_rules::is_public_token("abc' OR '1"));
var_dump(Job_post_rules::group_letter(1), Job_post_rules::group_letter(null));
?>
--EXPECT--
string(28) "Give the post a title first."
string(67) "Add at least one requirement first: candidates are matched on them."
NULL
bool(true)
bool(false)
string(1) "A"
string(0) ""
