--TEST--
the answer reads the meets probability and the expected fit
--FILE--
<?php
require __DIR__ . '/setup.inc';
$answer = Laya_client::read_answer(['answers' => [
    'meets' => ['type' => 'noul', 'noul' => 0.6831, 'confidence' => 0.6831],
    'fit' => ['type' => 'score', 'score' => 3.1234, 'probabilities' => []],
]]);
var_dump($answer);
?>
--EXPECT--
array(3) {
  ["meets"]=>
  float(0.6831)
  ["fit"]=>
  float(3.123)
  ["fit_label"]=>
  string(8) "good fit"
}
