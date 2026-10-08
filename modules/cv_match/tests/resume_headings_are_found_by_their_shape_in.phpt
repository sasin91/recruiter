--TEST--
résumé headings are found by their shape, in any language, and roles keep with their bullets
--FILE--
<?php
require __DIR__ . '/setup.inc';
$html = Pdf_writer::html('resume', "Max Muster\nBerlin\n\nBerufserfahrung\nEntwickler, Firma GmbH\n2019 - 2023\n- Baute APIs\n\nSprachen\n- Deutsch", 'Lebenslauf');
echo 'heading: ';
var_dump(str_contains($html, '<h2>Berufserfahrung</h2>'));
echo 'heading before bullets: ';
var_dump(str_contains($html, '<h2>Sprachen</h2>'));
echo 'role with its dates line: ';
var_dump(str_contains($html, "<h3>Entwickler, Firma GmbH</h3>\n<p>2019 - 2023</p>\n<ul>"));
echo 'Helvetica for WinAnsi text: ';
var_dump(str_contains($html, 'font-family: Helvetica'));
echo 'DejaVu outside WinAnsi: ';
var_dump(str_contains(Pdf_writer::html('resume', "Łukasz\n", 't'), 'DejaVu Sans'));
?>
--EXPECT--
heading: bool(true)
heading before bullets: bool(true)
role with its dates line: bool(true)
Helvetica for WinAnsi text: bool(true)
DejaVu outside WinAnsi: bool(true)
