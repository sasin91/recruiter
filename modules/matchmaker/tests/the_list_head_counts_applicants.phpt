--TEST--
the top of Matchmaker's list counts applicants and says which aren't scored
--FILE--
<?php
require_once __DIR__ . '/../Candidate_list_head.php';
var_dump(Candidate_list_head::from(['all' => 11, 'rejected' => 1, 'new' => 2, 'unscored' => 3, 'no_laya' => 1], true));
echo Candidate_list_head::from(['all' => 1, 'rejected' => 0, 'new' => 0, 'unscored' => 1, 'no_laya' => 2], false)['applicants'], "\n";
var_dump(Candidate_list_head::from(['all' => 0, 'rejected' => 0, 'new' => 0, 'unscored' => 0, 'no_laya' => 0], true));
?>
--EXPECT--
array(5) {
  ["applicants"]=>
  string(13) "12 applicants"
  ["has_applicants"]=>
  bool(true)
  ["new"]=>
  int(2)
  ["unscored"]=>
  string(21) "3 applications aren't"
  ["no_laya"]=>
  array(2) {
    ["were"]=>
    string(17) "1 application was"
    ["rank"]=>
    string(8) "it ranks"
  }
}
1 applicant
array(5) {
  ["applicants"]=>
  string(12) "0 applicants"
  ["has_applicants"]=>
  bool(false)
  ["new"]=>
  int(0)
  ["unscored"]=>
  string(0) ""
  ["no_laya"]=>
  NULL
}
