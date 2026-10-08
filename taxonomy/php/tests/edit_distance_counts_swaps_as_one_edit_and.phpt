--TEST--
edit distance counts swaps as one edit and gives up early
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump(Taxonomy::edit_distance('projektleder', 'projektleder'));
var_dump(Taxonomy::edit_distance('projektleder', 'projketleder'));
var_dump(Taxonomy::edit_distance('projektleder', 'projektleeder'));
var_dump(Taxonomy::edit_distance('ærlig', 'aerlig', 2));
var_dump(Taxonomy::edit_distance('sap', 'salgschef', 2));
var_dump(Taxonomy::typo_budget('sap'));
var_dump(Taxonomy::typo_budget('excel'));
var_dump(Taxonomy::typo_budget('projektleder'));
?>
--EXPECT--
int(0)
int(1)
int(1)
int(2)
int(3)
int(0)
int(1)
int(2)
