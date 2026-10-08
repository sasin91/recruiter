--TEST--
score follows the legacy ranker
--FILE--
<?php
require __DIR__ . '/setup.inc';
$groups = Cv_matcher::criteria([
    'job_title' => 'Backend developer',
    'job_title_en' => 'Backend developer',
    'company' => '',
    'requirements' => [$req('PHP'), $req('Go'), $req('React', ['required' => false])],
    'responsibilities' => [],
]);
$verdicts = ['r0' => ['verdict' => 'met'], 'r1' => ['verdict' => 'partial'], 'r2' => ['verdict' => 'missing'], 't0' => ['verdict' => 'met']];
$result = Cv_matcher::score($groups, $verdicts);
// requirements 75% of 150 = 113 (rounded), skills 0 of 50, title 30 of 30;
// responsibilities are empty and left out of the total.
var_dump([$result['rank'], $result['total']]);
var_dump($result['tag']);
var_dump(Cv_matcher::match_tag(0.85));
var_dump(Cv_matcher::match_tag(0.8));
var_dump(Cv_matcher::match_tag(0.59));
?>
--EXPECT--
array(2) {
  [0]=>
  int(143)
  [1]=>
  int(230)
}
string(6) "medium"
string(3) "top"
string(4) "good"
string(4) "poor"
