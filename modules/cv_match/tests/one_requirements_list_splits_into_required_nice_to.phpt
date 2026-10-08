--TEST--
one requirements list splits into required, nice to have and alternatives
--FILE--
<?php
require __DIR__ . '/setup.inc';
$job = [
    'job_title' => 'Kok',
    'job_title_en' => 'Cook',
    'requirements' => [
        $req('Uddannet kok', ['kind' => 'education', 'alt_group' => 'a']),
        $req('5 års erfaring', ['kind' => 'experience', 'alt_group' => 'a', 'min_years' => 5]),
        $req('Hygiejnebevis', ['kind' => 'certificate']),
        $req('Engelsk', ['required' => false]),
        $req('Selvstændig', ['kind' => 'soft_skill']),
    ],
    'responsibilities' => [],
];
$groups = Cv_matcher::criteria($job);
var_dump(array_column($groups['requirements'], 'text'));
var_dump(array_column($groups['skills'], 'text'));
var_dump(Cv_matcher::soft_skills($job));
var_dump(array_column(Cv_matcher::units($groups), 'id'));
$verdicts = Cv_matcher::combine($groups, ['r0' => ['verdict' => 'missing'], 'r1' => ['verdict' => 'met']]);
var_dump($verdicts['g0']['verdict']);
?>
--EXPECT--
array(2) {
  [0]=>
  string(30) "Uddannet kok / 5 års erfaring"
  [1]=>
  string(13) "Hygiejnebevis"
}
array(1) {
  [0]=>
  string(7) "Engelsk"
}
array(1) {
  [0]=>
  string(12) "Selvstændig"
}
array(5) {
  [0]=>
  string(2) "r0"
  [1]=>
  string(2) "r1"
  [2]=>
  string(2) "r2"
  [3]=>
  string(2) "r3"
  [4]=>
  string(2) "t0"
}
string(3) "met"
