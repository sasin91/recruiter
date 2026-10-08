--TEST--
kind narrows the result
--FILE--
<?php
require __DIR__ . '/setup.inc';
$results = $tiny->search('Projektleder', 'skill', 5);
var_dump(count($results) > 0);
foreach ($results as $r) {
    var_dump($r['term']['kind']);
}
?>
--EXPECT--
bool(true)
string(5) "skill"
