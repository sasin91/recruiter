--TEST--
an exact name inside a longer phrase wins and reports exact
--FILE--
<?php
require __DIR__ . '/setup.inc';
$r = $top('Erfaring med SAP');
var_dump($r['term']['id']);
var_dump(Taxonomy::match_method($r, 'Erfaring med SAP'));
?>
--EXPECT--
int(1)
string(5) "exact"
