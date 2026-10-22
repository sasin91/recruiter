--TEST--
the Laya summary is the bake-off's English sentence pair
--FILE--
<?php
require __DIR__ . '/setup.inc';
$job = [
    'job_title' => 'Backend-udvikler',
    'job_title_en' => 'Backend developer',
    'requirements' => [$req('PHP'), $req('Dansk', ['english' => 'Danish', 'kind' => 'language']), $req('Go')],
    'responsibilities' => [],
];
$verdicts = ['r0' => ['verdict' => 'met'], 'r1' => ['verdict' => 'partial']];
var_dump(Cv_matcher::laya_summary($job, Cv_matcher::criteria($job), $verdicts));

// A required location counts as a required qualification.
$job['requirements'][] = $req('Located close to Copenhagen', ['kind' => 'location']);
var_dump(Cv_matcher::laya_summary($job, Cv_matcher::criteria($job), $verdicts + ['r3' => ['verdict' => 'missing', 'by' => 'llm']]));
// One nobody judged (no AI key) is left out: it isn't known to be missing.
var_dump(Cv_matcher::laya_summary($job, Cv_matcher::criteria($job), $verdicts + ['r3' => ['verdict' => 'missing', 'by' => 'none']]));
?>
--EXPECT--
string(118) "Job: Backend developer. The candidate meets 1 of 3 required qualifications. Missing requirements: Danish (partly), Go."
string(147) "Job: Backend developer. The candidate meets 1 of 4 required qualifications. Missing requirements: Danish (partly), Go, Located close to Copenhagen."
string(118) "Job: Backend developer. The candidate meets 1 of 3 required qualifications. Missing requirements: Danish (partly), Go."
