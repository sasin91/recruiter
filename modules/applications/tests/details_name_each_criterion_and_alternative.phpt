--TEST--
score details: one row per criterion, one per OR-alternative, with the post's term ids
--FILE--
<?php
require __DIR__ . '/setup.inc';
$verdicts = Cv_matcher::combine($groups, [
    'r0' => verdict('met', 'taxonomy', 'exact', 'PHP 8'),
    'r1' => verdict('missing', 'none', 'not_found'),
    'r2' => verdict('partial', 'llm', 'judgement', 'Lumen'),
    'r3' => verdict('met', 'rule', 'years', '5 years'),
]);
foreach (Application_scoring::details($groups, $verdicts, $job, $rows) as $d) {
    printf("%-6s term %-4s %-8s %-10s w%-3d rank %.1f %s %s\n", $d['criterion'], var_export($d['job_post_term_id'], true), $d['stage'], $d['rule'], $d['weight'], $d['rank'], $d['passed'], $d['reason']);
}
?>
--EXPECT--
r0     term 11   taxonomy exact      w150 rank 1.0 1 Because met. CV: "PHP 8"
g0     term NULL llm      judgement  w150 rank 0.5 0 Because partial. CV: "Lumen"
g0/r1  term 12   none     not_found  w150 rank 0.0 0 Because missing.
g0/r2  term 13   llm      judgement  w150 rank 0.5 0 Because partial. CV: "Lumen"
r3     term 14   rule     years      w150 rank 1.0 1 Because met.
r5     term 16   none     not_found  w50  rank 0.0 0 No verdict.
t0     term 10   none     not_found  w30  rank 0.0 0 No verdict.
a0     term 17   none     not_found  w30  rank 0.0 0 No verdict.
