--TEST--
a level goes to the model
--FILE--
<?php
require __DIR__ . '/setup.inc';
[$item] = Cv_matcher::criteria(['requirements' => [$req('Dansk', ['kind' => 'language', 'min_level' => 'fluent'])], 'responsibilities' => []])['requirements'];
var_dump($item['text']);
var_dump($matcher->decide($item));
?>
--EXPECT--
string(14) "Dansk (fluent)"
NULL
