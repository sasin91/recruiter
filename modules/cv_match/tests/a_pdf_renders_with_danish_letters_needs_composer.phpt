--TEST--
a PDF renders with Danish letters (needs composer install)
--SKIPIF--
<?php if (!is_file(__DIR__ . '/../../../packages/autoload.php')) echo 'skip packages/ not installed (composer install)'; ?>
--FILE--
<?php
require __DIR__ . '/setup.inc';
$pdf = Pdf_writer::pdf('resume', $resume, 'Résumé: Acme');
echo 'PDF header: ';
var_dump(str_starts_with($pdf, '%PDF-'));
echo 'has content: ';
var_dump(strlen($pdf) > 1000);
echo 'structured résumé: ';
var_dump(str_starts_with(Pdf_writer::resume_pdf(Tailored_resume::clean($fields), 'Résumé'), '%PDF-'));
?>
--EXPECT--
PDF header: bool(true)
has content: bool(true)
structured résumé: bool(true)
