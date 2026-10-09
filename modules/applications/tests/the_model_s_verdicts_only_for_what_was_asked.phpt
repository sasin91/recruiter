--TEST--
the model's answer is kept only for the ids asked about, with a known verdict
--FILE--
<?php
require __DIR__ . '/setup.inc';
$judged = Application_scoring::judged(['verdicts' => [
    ['id' => 'r2', 'verdict' => 'met', 'evidence' => 'Laravel 10', 'reason' => 'Named.'],
    ['id' => 'r0', 'verdict' => 'met', 'evidence' => '', 'reason' => 'Not asked.'],
    ['id' => 'a0', 'verdict' => 'maybe', 'evidence' => '', 'reason' => 'Not a verdict.'],
]], ['r2', 'a0']);
echo json_encode($judged), "\n";
?>
--EXPECT--
{"r2":{"verdict":"met","by":"llm","method":"judgement","evidence":"Laravel 10","reason":"Named."}}
