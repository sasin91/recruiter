--TEST--
years of work come from the date ranges, overlaps and education left out
--FILE--
<?php
require __DIR__ . '/setup.inc';
$now = mktime(0, 0, 0, 10, 1, 2026);
var_dump(Free_reader::years_worked("January 2017 – February 2020\nApril 2020 – January 2023", $now));
var_dump(Free_reader::years_worked("2019 - 2022\n2020 - 2021", $now));
var_dump(Free_reader::years_worked("03/2024 - nu", $now));
var_dump(Free_reader::years_asked('Flere års erfaring med salg'));
var_dump(Free_reader::years_asked('5 years of experience with Go'));
var_dump(Free_reader::years_asked('Kørekort B'));
?>
--EXPECT--
int(6)
int(4)
int(2)
int(3)
int(5)
int(0)
