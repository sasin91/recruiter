--TEST--
what the taxonomy can't decide is left for the model
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump($verdict(['text' => 'React']));
var_dump($verdict(['text' => 'Ejerskab og nysgerrighed']));
var_dump($matcher->decide(['id' => 'a0', 'kind' => 'responsibility', 'text' => 'PHP']));
?>
--EXPECT--
NULL
NULL
NULL
