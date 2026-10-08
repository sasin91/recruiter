--TEST--
keeps æ, ø and å through normalisation
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump(Static_model::prepare('Ærlighed på færøsk'));
// A decomposed å (a + combining ring) is composed, not stripped.
var_dump(Static_model::prepare($decomposed));
var_dump(Taxonomy::words('Kørekort B, Århus'));
var_dump(Taxonomy::words($decomposed));
?>
--EXPECT--
string(29) "▁Ærlighed▁på▁færøsk"
string(9) "▁årsag"
array(3) {
  [0]=>
  string(9) "kørekort"
  [1]=>
  string(1) "b"
  [2]=>
  string(6) "århus"
}
array(1) {
  [0]=>
  string(6) "årsag"
}
