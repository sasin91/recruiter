--TEST--
a key is used for everything until a purpose is switched off
--FILE--
<?php
require_once __DIR__ . '/../../llm/tests/setup.inc';
if (!class_exists('Model')) {
    class Model {}
}
require_once __DIR__ . '/../Account_model.php';
// No rows yet: all on.
var_dump(Account_model::uses_from_rows([]));
var_dump(Account_model::uses_from_rows([['purpose' => 'application', 'enabled' => '0'], ['purpose' => 'resume', 'enabled' => '1']]));
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
