--TEST--
a structured résumé is cleaned: trimmed, entries without a title and non-strings dropped, headings defaulted
--FILE--
<?php
require __DIR__ . '/setup.inc';
$resume = Tailored_resume::clean($fields);
var_dump($resume['name']);
echo 'entries: ';
var_dump(count($resume['experience']));
echo 'bullets: ';
var_dump($resume['experience'][0]['bullets']);
var_dump($resume['links']);
echo 'English fallback: ';
var_dump($resume['languages_heading']);
echo 'no entries: ';
var_dump(Tailored_resume::clean(['name' => 'X']));
echo 'every field required: ';
var_dump(array_keys(Tailored_resume::schema()['properties']) === Tailored_resume::schema()['required']);
?>
--EXPECT--
string(15) "Mette Sørensen"
entries: int(1)
bullets: array(1) {
  [0]=>
  string(22) "Byggede en <ordre-API>"
}
array(1) {
  [0]=>
  string(16) "github.com/mette"
}
English fallback: string(9) "Languages"
no entries: NULL
every field required: bool(true)
