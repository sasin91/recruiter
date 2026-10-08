--TEST--
experience is arithmetic
--FILE--
<?php
require __DIR__ . '/setup.inc';
$item = fn(int $min_years) => ['id' => 'r9', 'kind' => 'experience', 'min_years' => $min_years, 'text' => "$min_years years"];
var_dump($matcher->decide($item(5))['verdict']);
var_dump($matcher->decide($item(12))['verdict']);
var_dump($matcher->decide($item(20))['verdict']);
?>
--EXPECT--
string(3) "met"
string(7) "partial"
string(7) "missing"
