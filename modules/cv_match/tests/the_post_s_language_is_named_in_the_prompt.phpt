--TEST--
the post's language is named outright, whatever the CV around it is in
--FILE--
<?php
require_once __DIR__ . '/../Post_language.php';
$english = "Backend Developer\n\nWe are looking for a developer who will join our team and work with PHP and Laravel. You have experience with APIs and you are comfortable owning a product from idea to release.\nGem job  Ansøg  Del på LinkedIn";
$danish = "Backend-udvikler\n\nVi søger en udvikler, som har erfaring med PHP og Laravel. Du skal arbejde tæt sammen med vores team og være med til at bygge nye løsninger.";
var_dump(Post_language::of($english), Post_language::of($danish), Post_language::of('Backend Developer'));
echo Post_language::rule($english, 'application'), "\n";
echo Post_language::rule('PHP, Laravel', 'CV'), "\n";
?>
--EXPECT--
string(7) "English"
string(6) "Danish"
NULL
Write the application in English, because the job post is in English, even where the CV, the requirements or the notes are in another language.
Write the CV in the language of the job post, whatever language the CV, the requirements or the notes are in.
