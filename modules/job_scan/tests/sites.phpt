--TEST--
Job_search_rules::sites reads the sites box as domains
--FILE--
<?php
require __DIR__ . '/../Job_search_rules.php';
var_dump(Job_search_rules::sites("jobnet.dk\nhttps://www.Jobindex.dk/jobsoegning?q=php, thehub.io jobnet.dk"));
var_dump(Job_search_rules::sites("not a site"));
var_dump(Job_search_rules::sites(''));
?>
--EXPECT--
array(2) {
  [0]=>
  array(3) {
    [0]=>
    string(9) "jobnet.dk"
    [1]=>
    string(11) "jobindex.dk"
    [2]=>
    string(9) "thehub.io"
  }
  [1]=>
  array(0) {
  }
}
array(2) {
  [0]=>
  array(0) {
  }
  [1]=>
  array(3) {
    [0]=>
    string(3) "not"
    [1]=>
    string(1) "a"
    [2]=>
    string(4) "site"
  }
}
array(2) {
  [0]=>
  array(0) {
  }
  [1]=>
  array(0) {
  }
}
