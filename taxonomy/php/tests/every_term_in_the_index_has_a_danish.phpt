--TEST--
every term in the index has a Danish preferred label
--FILE--
<?php
require __DIR__ . '/setup.inc';
$without = array_filter($built->terms, fn(array $term) => $preferred_da($term) === null);
var_dump(array_column($without, 'id'));
?>
--EXPECT--
array(0) {
}
