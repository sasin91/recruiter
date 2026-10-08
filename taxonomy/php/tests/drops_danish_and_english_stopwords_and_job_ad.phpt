--TEST--
drops Danish and English stopwords and job-ad qualifiers, never 'it'
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump(Taxonomy::content_words('Erfaring med SAP og Excel'));
var_dump(Taxonomy::content_words('strong knowledge of IT'));
var_dump(Taxonomy::content_words('Kendskab til IT-support'));
?>
--EXPECT--
array(2) {
  [0]=>
  string(3) "sap"
  [1]=>
  string(5) "excel"
}
array(1) {
  [0]=>
  string(2) "it"
}
array(2) {
  [0]=>
  string(2) "it"
  [1]=>
  string(7) "support"
}
