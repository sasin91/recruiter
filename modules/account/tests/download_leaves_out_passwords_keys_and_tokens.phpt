--TEST--
Download my data leaves out passwords, encrypted API keys and job search tokens, and keeps the rest
--FILE--
<?php
require_once __DIR__ . '/../../../engine/Trongate.php';
require_once __DIR__ . '/../../../engine/Model.php';
require_once __DIR__ . '/../Account_model.php';
var_dump(Account_model::without_secrets([
    ['id' => 1, 'email' => 'a@example.com', 'password' => '$2y$11$hash'],
    ['provider' => 'openai', 'api_key_encrypted' => 'c2VjcmV0', 'key_hint' => 'abcd'],
    ['name' => 'PHP', 'api_token_hash' => str_repeat('a', 64)],
]));
?>
--EXPECT--
array(3) {
  [0]=>
  array(2) {
    ["id"]=>
    int(1)
    ["email"]=>
    string(13) "a@example.com"
  }
  [1]=>
  array(2) {
    ["provider"]=>
    string(6) "openai"
    ["key_hint"]=>
    string(4) "abcd"
  }
  [2]=>
  array(1) {
    ["name"]=>
    string(3) "PHP"
  }
}
