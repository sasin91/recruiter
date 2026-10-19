--TEST--
the joblog deadline is read as day-month-year from Danish and English posts
--FILE--
<?php
require __DIR__ . '/../Joblog.php';
$saved = mktime(12, 0, 0, 10, 19, 2026);
foreach ([
    'Ansøgningsfrist: 31. oktober 2026',
    'Søg senest den 2.11.',
    'Deadline: November 5th, 2026',
    'Sidste ansøgningsdag er 2026-11-30 kl. 12.00',
    'Ansøgningsfrist 15/1, vi holder samtaler løbende',
    'Ansøgningsfrist: Løbende',
    'Frist: snarest. Start 1. januar',
    'Tiltrædelse 1. december 2026',
] as $text) {
    echo Joblog::deadline($text, $saved), "\n";
}
?>
--EXPECT--
31-10-2026
02-11-2026
05-11-2026
30-11-2026
15-01-2027



