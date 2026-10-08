--TEST--
the résumé PDF page has the name, contact lines, sections, roles and bullets
--FILE--
<?php
require __DIR__ . '/setup.inc';
$html = Pdf_writer::html('resume', $resume, 'Résumé: Acme');
echo 'name: ';
var_dump(str_contains($html, '<h1>Jonas Hansen</h1>'));
echo 'contact: ';
var_dump(str_contains($html, '<p class="contact">jonas@example.com | +45 12 34 56 78</p>'));
echo 'sections: ';
var_dump(substr_count($html, '<h2>'));
echo 'heading: ';
var_dump(str_contains($html, '<h2>Erfaring</h2>'));
echo 'role: ';
var_dump(str_contains($html, '<h3>Tech Lead, Acme ApS (2019 - 2023)</h3>'));
echo 'bullet, escaped: ';
var_dump(str_contains($html, '<li>Ledte et team på 4 &lt;udviklere&gt;</li>'));
echo 'paragraph: ';
var_dump(str_contains($html, '<p>Backend-udvikler med 9 års erfaring i PHP &amp; Laravel.</p>'));
?>
--EXPECT--
name: bool(true)
contact: bool(true)
sections: int(3)
heading: bool(true)
role: bool(true)
bullet, escaped: bool(true)
paragraph: bool(true)
