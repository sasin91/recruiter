--TEST--
the deterministic score leaves out the model's verdicts; llm and combined count them
--FILE--
<?php
require __DIR__ . '/setup.inc';
$verdicts = [
    't0' => verdict('met', 'taxonomy'),
    'r0' => verdict('met', 'taxonomy'),
    'r1' => verdict('missing', 'none', 'not_found'),
    'r2' => verdict('met', 'llm', 'judgement'),
    'r3' => verdict('met', 'rule', 'years'),
    'r5' => verdict('partial', 'llm', 'judgement'),
    'a0' => verdict('missing', 'llm', 'judgement'),
];
$s = Application_scoring::scores($groups, $verdicts);
printf("deterministic %.4f, llm %.4f, combined %.4f, %s\n", $s['deterministic'], $s['llm'], $s['combined'], $s['tag']);

$taxonomy_only = array_map(fn($v) => $v['by'] === 'llm' ? Application_scoring::not_found('No key.') : $v, $verdicts);
$s = Application_scoring::scores($groups, $taxonomy_only);
printf("no model: deterministic %.4f, llm %s, combined %.4f\n", $s['deterministic'], var_export($s['llm'], true), $s['combined']);
?>
--EXPECT--
deterministic 0.4962, llm 0.7885, combined 0.7885, medium
no model: deterministic 0.4962, llm NULL, combined 0.4962
