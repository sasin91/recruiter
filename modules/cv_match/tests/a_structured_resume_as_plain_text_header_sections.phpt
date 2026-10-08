--TEST--
a structured résumé as plain text: header, sections under their headings, entries and bullets
--FILE--
<?php
require __DIR__ . '/setup.inc';
$text = Tailored_resume::text(Tailored_resume::clean($fields));
echo 'header: ';
var_dump(str_starts_with($text, "Mette Sørensen\nBackend-udvikler\nAarhus · +45 22 33 44 55 · mette@example.dk\ngithub.com/mette\n\n"));
echo 'entry: ';
var_dump(str_contains($text, "Erfaring\n\nSenior PHP-udvikler · Nordlys A/S\napril 2021 – nu — Aarhus\nLogistikplatform\n- Byggede en <ordre-API>"));
echo 'skills: ';
var_dump(str_contains($text, "Kompetencer\n- PHP\n- Laravel"));
echo 'education: ';
var_dump(str_contains($text, "Uddannelse\n\nDatamatiker · EAAA\n2015 – 2017 — Aarhus"));
?>
--EXPECT--
header: bool(true)
entry: bool(true)
skills: bool(true)
education: bool(true)
