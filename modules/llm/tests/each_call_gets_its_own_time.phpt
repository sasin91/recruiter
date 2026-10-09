--TEST--
each call gets its own time, past PHP's 30 seconds
--INI--
max_execution_time=30
--FILE--
<?php
require __DIR__ . '/setup.inc';
Llm::allow_time(Llm::CALL_SECONDS);
var_dump((int) ini_get('max_execution_time') > Llm::CALL_SECONDS);
?>
--EXPECT--
bool(true)
