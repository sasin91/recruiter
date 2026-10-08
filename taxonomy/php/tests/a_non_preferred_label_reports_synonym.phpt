--TEST--
a non-preferred label reports synonym
--FILE--
<?php
require __DIR__ . '/setup.inc';
$r = $top('B-kørekort');
var_dump($r['term']['id']);
var_dump(Taxonomy::match_method($r, 'B-kørekort'));
?>
--EXPECT--
int(3)
string(7) "synonym"
