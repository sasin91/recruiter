--TEST--
text with no known tokens has no vector
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump($model->encode('')['vector']);
?>
--EXPECT--
NULL
