--TEST--
the list order: Laya's "meets every requirement" first, then its fit, then the score; unsure answers flagged
--FILE--
<?php
require __DIR__ . '/setup.inc';
echo implode(' ', Application_scoring::order([
    ['id' => 1, 'meets' => 0.30, 'fit' => 3.9, 'combined' => 0.95, 'submitted_at' => 100],
    ['id' => 2, 'meets' => 0.80, 'fit' => 2.0, 'combined' => 0.50, 'submitted_at' => 100],
    ['id' => 3, 'meets' => 0.55, 'fit' => 3.0, 'combined' => 0.40, 'submitted_at' => 100],
    ['id' => 4, 'meets' => null, 'fit' => null, 'combined' => 0.99, 'submitted_at' => 100],
    ['id' => 5, 'meets' => 0.90, 'fit' => 3.0, 'combined' => 0.40, 'submitted_at' => 50],
    ['id' => 6, 'meets' => 0.30, 'fit' => 3.9, 'combined' => 0.96, 'submitted_at' => 200],
])), "\n";
foreach ([null, 0.39, 0.4, 0.5, 0.6, 0.61] as $p) {
    echo var_export($p, true), ': ', Application_scoring::needs_human($p) ? 'unsure' : 'clear', "\n";
}
?>
--EXPECT--
5 3 2 6 1 4
NULL: clear
0.39: clear
0.4: unsure
0.5: unsure
0.6: unsure
0.61: clear
