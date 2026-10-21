--TEST--
saved matches become rows of the page's list
--FILE--
<?php
require_once __DIR__ . '/../Saved_match_row.php';
// The page puts the date in the reader's time zone; saved_at is the moment, in UTC.
date_default_timezone_set('UTC');
$match = ['id' => '7', 'job_title' => 'Backend-udvikler', 'company' => 'Nordisk Fragt', 'cv_name' => 'CV', 'score' => '0.814', 'tag' => 'good',
    'has_application' => '0', 'has_resume' => '0', 'created_at' => (string) gmmktime(23, 30, 0, 10, 21, 2026), 'jobnet_logged_at' => null];
var_dump(array_map([Saved_match_row::class, 'from'], [
    $match,
    ['id' => '8', 'job_title' => '', 'company' => '', 'cv_name' => 'CV (tailored résumé)', 'has_application' => '1', 'has_resume' => '1', 'jobnet_logged_at' => '1'] + $match,
]));
?>
--EXPECT--
array(2) {
  [0]=>
  array(7) {
    ["id"]=>
    int(7)
    ["tag"]=>
    string(4) "good"
    ["title"]=>
    string(33) "Backend-udvikler · Nordisk Fragt"
    ["about"]=>
    string(8) "81% good"
    ["saved_at"]=>
    string(20) "2026-10-21T23:30:00Z"
    ["saved_on"]=>
    string(11) "21 Oct 2026"
    ["done"]=>
    string(0) ""
  }
  [1]=>
  array(7) {
    ["id"]=>
    int(8)
    ["tag"]=>
    string(4) "good"
    ["title"]=>
    string(8) "Untitled"
    ["about"]=>
    string(29) "81% good · tailored résumé"
    ["saved_at"]=>
    string(20) "2026-10-21T23:30:00Z"
    ["saved_on"]=>
    string(11) "21 Oct 2026"
    ["done"]=>
    string(36) "application · résumé · on Jobnet"
  }
}
