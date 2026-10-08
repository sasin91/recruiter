--TEST--
the application PDF page keeps paragraphs and the sign-off lines
--FILE--
<?php
require __DIR__ . '/setup.inc';
$html = Pdf_writer::html('application', "Kære Acme\r\n\r\nJeg søger **stillingen**.\n\nVenlig hilsen\nJonas Hansen", 'Job application');
var_dump(substr_count($html, '<p>'));
echo 'markdown bold dropped: ';
var_dump(str_contains($html, '<p>Jeg søger stillingen.</p>'));
echo 'line breaks kept: ';
var_dump(str_contains($html, "<p>Venlig hilsen<br>\nJonas Hansen</p>"));
?>
--EXPECT--
int(3)
markdown bold dropped: bool(true)
line breaks kept: bool(true)
