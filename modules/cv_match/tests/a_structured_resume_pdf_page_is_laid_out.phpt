--TEST--
a structured résumé PDF page is laid out from the fields, escaped, each entry kept together
--FILE--
<?php
require __DIR__ . '/setup.inc';
$html = Pdf_writer::resume_fields_html(Tailored_resume::clean($fields), 'Résumé');
echo 'name: ';
var_dump(str_contains($html, '<h1>Mette Sørensen</h1>'));
echo 'title: ';
var_dump(str_contains($html, '<p class="title">Backend-udvikler</p>'));
echo 'heading kept with the first entry: ';
var_dump(str_contains($html, "<div class=\"keep\">\n<h2>Erfaring</h2>\n<div class=\"entry\">\n<h3>Senior PHP-udvikler · Nordlys A/S</h3>"));
echo 'meta: ';
var_dump(str_contains($html, '<p class="meta">april 2021 – nu — Aarhus</p>'));
echo 'escaped bullet: ';
var_dump(str_contains($html, '<li>Byggede en &lt;ordre-API&gt;</li>'));
echo 'languages: ';
var_dump(str_contains($html, '<h2>Languages</h2>'));
?>
--EXPECT--
name: bool(true)
title: bool(true)
heading kept with the first entry: bool(true)
meta: bool(true)
escaped bullet: bool(true)
languages: bool(true)
