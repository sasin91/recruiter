--TEST--
encodes to a unit vector of the model's dimension
--FILE--
<?php
require __DIR__ . '/setup.inc';
$e = $model->encode('Projektleder med erfaring i byggeriet');
var_dump(count($e['vector']));
var_dump(abs(Static_model::cosine($e['vector'], $e['vector']) - 1) < 1e-5);
var_dump($e['unknown']);
?>
--EXPECT--
int(128)
bool(true)
int(0)
