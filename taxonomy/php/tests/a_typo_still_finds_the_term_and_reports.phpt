--TEST--
a typo still finds the term and reports fuzzy
--FILE--
<?php
require __DIR__ . '/setup.inc';
$r = $top('projektleeder');
var_dump($r['term']['id']);
var_dump(Taxonomy::match_method($r, 'projektleeder'));
?>
--EXPECT--
int(2)
string(5) "fuzzy"
