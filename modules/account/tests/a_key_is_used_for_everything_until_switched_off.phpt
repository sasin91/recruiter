--TEST--
a key is used for everything until a use is switched off
--FILE--
<?php
require_once __DIR__ . '/../../llm/tests/setup.inc';
if (!class_exists('Model')) {
    class Model {}
}
require_once __DIR__ . '/../Account_model.php';
// A row from before the switches existed: all on.
var_dump(Account_model::uses(['provider' => 'openai']));
var_dump(Account_model::uses(['use_for_resume' => '1', 'use_for_application' => '0', 'use_for_job_evaluation' => '1']));
?>
--EXPECT--
array(3) {
  ["resume"]=>
  bool(true)
  ["application"]=>
  bool(true)
  ["job_evaluation"]=>
  bool(true)
}
array(3) {
  ["resume"]=>
  bool(true)
  ["application"]=>
  bool(false)
  ["job_evaluation"]=>
  bool(true)
}
