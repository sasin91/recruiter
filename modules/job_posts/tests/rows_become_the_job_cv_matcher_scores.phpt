--TEST--
rows become the job post Cv_matcher scores, each requirement pointing back at its row
--FILE--
<?php
require __DIR__ . '/setup.inc';
$job = Job_post_rules::to_job(Job_post_rules::from_reading($reading));
echo "{$job['job_title']} / {$job['job_title_en']}\n";
foreach ($job['requirements'] as $r) {
    echo "row {$r['row']}: {$r['value']} ({$r['kind']}) required=", (int) $r['required'], " alt_group='{$r['alt_group']}' years={$r['min_years']} level='{$r['min_level']}'\n";
}
var_dump($job['responsibilities']);
?>
--EXPECT--
Kok / Chef
row 1: Uddannet kok (education) required=1 alt_group='1' years=0 level=''
row 2: 5 års erfaring (experience) required=1 alt_group='1' years=5 level=''
row 3: Kørekort B (certificate) required=1 alt_group='' years=0 level=''
row 4: Engelsk (language) required=0 alt_group='' years=0 level='fluent'
row 5: Glad (soft_skill) required=1 alt_group='' years=0 level=''
array(1) {
  [0]=>
  string(15) "Frokost til 200"
}
