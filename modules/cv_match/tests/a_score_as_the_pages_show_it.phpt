--TEST--
a score as the pages show it: percent, tag and tag name
--FILE--
<?php
require_once __DIR__ . '/../Match_score.php';
var_dump(Match_score::from('0.814', 'good'));
var_dump(Match_score::from(null, null));
var_dump(Match_score::percent(0.995), Match_score::tag_name('poor'), Match_score::tag_name('odd'), Match_score::short_label(0.6, 'medium'));
?>
--EXPECT--
array(3) {
  ["percent"]=>
  int(81)
  ["tag"]=>
  string(4) "good"
  ["tag_name"]=>
  string(10) "Good match"
}
NULL
int(100)
string(10) "Weak match"
string(3) "odd"
string(10) "60% medium"
