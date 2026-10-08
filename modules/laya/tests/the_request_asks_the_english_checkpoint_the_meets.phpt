--TEST--
the request asks the English checkpoint the meets and fit questions
--FILE--
<?php
require __DIR__ . '/setup.inc';
$request = Laya_client::request('Job: Backend developer. The candidate meets 2 of 3 required qualifications. Missing requirements: Go.');
var_dump($request['model']);
var_dump(array_keys($request['questions']));
var_dump($request['questions']['meets']['type']);
var_dump($request['questions']['fit']['type']);
var_dump(count($request['questions']['fit']['criteria']));
?>
--EXPECT--
string(7) "english"
array(2) {
  [0]=>
  string(5) "meets"
  [1]=>
  string(3) "fit"
}
string(4) "noul"
string(5) "score"
int(5)
