--TEST--
the built index answers real Danish phrases
--FILE--
<?php
require __DIR__ . '/setup.inc';
$cases = [
    ['Erfaring med SAP', 'skill', 'SAP'],
    ['projektleeder', 'title', 'Projektleder'],
    ['Project manager', 'title', 'Projektleder'],
    ['kørekort B', 'skill', 'Kørekort B'],
    ['regnskab', 'skill', 'Regnskab'],
    ['Software developer', 'role', 'Softwareudvikler'],
    ['Excel', 'skill', 'Excel'],
];
foreach ($cases as [$query, $kind, $expected]) {
    $r = $built->search($query, $kind, 1)[0];
    echo $query, ': ';
    var_dump($preferred_da($r['term']));
}
?>
--EXPECT--
Erfaring med SAP: string(3) "SAP"
projektleeder: string(12) "Projektleder"
Project manager: string(12) "Projektleder"
kørekort B: string(11) "Kørekort B"
regnskab: string(8) "Regnskab"
Software developer: string(16) "Softwareudvikler"
Excel: string(5) "Excel"
