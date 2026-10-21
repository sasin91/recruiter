--TEST--
saved matches become rows of the page's list
--FILE--
<?php
require_once __DIR__ . '/../Saved_match_row.php';
$match = ['id' => '7', 'job_title' => 'Backend-udvikler', 'company' => 'Nordisk Fragt', 'cv_name' => 'CV', 'score' => '0.814', 'tag' => 'good',
    'has_application' => '0', 'has_resume' => '0', 'created_at' => (string) mktime(12, 0, 0, 10, 21, 2026), 'jobnet_logged_at' => null];
var_dump(array_map([Saved_match_row::class, 'from'], [
    $match,
    ['id' => '8', 'job_title' => '', 'company' => '', 'cv_name' => 'CV (tailored résumé)', 'has_application' => '1', 'has_resume' => '1', 'jobnet_logged_at' => '1'] + $match,
]));
?>
--EXPECT--
array(2) {
  [0]=>
  array(4) {
    ["id"]=>
    int(7)
    ["tag"]=>
    string(4) "good"
    ["title"]=>
    string(33) "Backend-udvikler · Nordisk Fragt"
    ["meta"]=>
    string(23) "81% good · 21 Oct 2026"
  }
  [1]=>
  array(4) {
    ["id"]=>
    int(8)
    ["tag"]=>
    string(4) "good"
    ["title"]=>
    string(8) "Untitled"
    ["meta"]=>
    string(84) "81% good · tailored résumé · 21 Oct 2026 · application · résumé · on Jobnet"
  }
}
