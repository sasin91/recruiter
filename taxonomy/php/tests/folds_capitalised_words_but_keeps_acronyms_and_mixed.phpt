--TEST--
folds capitalised words but keeps acronyms and mixed case
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump(Static_model::fold_case('Regnskab og Økonomi'));
var_dump(Static_model::fold_case('SAP, IT-Chef, iOS, JavaScript, PowerPoint'));
$a = $model->encode('Regnskab')['vector'];
$b = $model->encode('regnskab')['vector'];
var_dump(Static_model::cosine($a, $b) > 0.99);
?>
--EXPECT--
string(20) "regnskab og økonomi"
string(41) "SAP, IT-chef, iOS, JavaScript, PowerPoint"
bool(true)
