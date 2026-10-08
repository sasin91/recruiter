--TEST--
the taxonomy decides names it knows
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump($verdict(['text' => 'Erfaring med Laravel'])['verdict']);
var_dump($verdict(['text' => 'Kubernets'])['method']);
var_dump($verdict(['text' => 'PHP'])['method']);
?>
--EXPECT--
string(3) "met"
string(5) "fuzzy"
string(5) "exact"
