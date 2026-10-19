--TEST--
the job post PDF page has its title, its link and its lists as bullets
--FILE--
<?php
require __DIR__ . '/../Pdf_writer.php';
$html = Pdf_writer::html('job', "https://example.dk/job/1\n\nVi søger en <udvikler>.\nDu har:\n- PHP\n- Laravel\n\nSøg nu.", 'Udvikler · Acme');
preg_match('~<body>\s*(.*?)\s*</body>~s', $html, $m);
echo $m[1], "\n";
echo Pdf_writer::file_name('job', 'Udvikler', 'Acme'), "\n";
echo Pdf_writer::file_name('cv', 'Udvikler', 'Acme'), "\n";
?>
--EXPECT--
<div class="job">
<h1>Udvikler · Acme</h1>
<p>https://example.dk/job/1</p>
<p>Vi søger en &lt;udvikler&gt;.</p>
<p>Du har:</p>
<ul>
<li>PHP</li>
<li>Laravel</li>
</ul>
<p>Søg nu.</p>
</div>
Job ad - Udvikler - Acme.pdf
CV - Udvikler - Acme.pdf
