--TEST--
a fit past the scale is clamped to it
--FILE--
<?php
require __DIR__ . '/setup.inc';
$answer = Laya_client::read_answer(['answers' => ['meets' => ['noul' => 0], 'fit' => ['score' => 4.2]]]);
var_dump($answer['fit']);
var_dump($answer['fit_label']);
?>
--EXPECT--
float(4)
string(13) "excellent fit"
