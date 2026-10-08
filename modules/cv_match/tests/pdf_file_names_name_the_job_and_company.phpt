--TEST--
PDF file names name the job and company without unsafe characters
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump(Pdf_writer::file_name('resume', 'Udvikler / PHP', 'Acme: "ApS"'));
var_dump(Pdf_writer::file_name('application', '', ''));
?>
--EXPECT--
string(36) "Resume - Udvikler PHP - Acme ApS.pdf"
string(15) "Application.pdf"
