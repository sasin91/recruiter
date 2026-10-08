--TEST--
Danish and English names of one thing are close, unrelated things are not
--FILE--
<?php
require __DIR__ . '/setup.inc';
$close = Static_model::cosine($model->encode('Projektleder')['vector'], $model->encode('Project manager')['vector']);
$far = Static_model::cosine($model->encode('Projektleder')['vector'], $model->encode('Sygeplejerske')['vector']);
var_dump($close > $far + 0.2);
?>
--EXPECT--
bool(true)
