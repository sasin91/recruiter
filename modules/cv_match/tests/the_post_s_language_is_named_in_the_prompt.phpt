--TEST--
the post's language is named outright, whatever the CV around it is in
--FILE--
<?php
require_once __DIR__ . '/../Post_language.php';
$english = "Backend Developer\n\nWe are looking for a developer who will join our team and work with PHP and Laravel. You have experience with APIs and you are comfortable owning a product from idea to release.\nGem job  Ansøg  Del på LinkedIn";
$danish = "Backend-udvikler\n\nVi søger en udvikler, som har erfaring med PHP og Laravel. Du skal arbejde tæt sammen med vores team og være med til at bygge nye løsninger.";
$norwegian = "Backend-utvikler\n\nVi søker en utvikler som har erfaring med PHP og Laravel. Du vil jobbe tett med teamet vårt, og vi ønsker at du har god kunnskap om API-er. Send søknad via skjemaet, noen spørsmål kan rettes til deg av oss.";
$bokmal = "Vi søker en utvikler som vil jobbe med PHP. Du skal være med på å bygge gode løsninger. Vi ønsker at du har erfaring og kan arbeide tett med vårt team.";
$swedish = "Backendutvecklare\n\nVi söker en utvecklare som har erfarenhet av PHP och Laravel. Du kommer att arbeta nära vårt team och det är viktigt att du inte är rädd för att ta ansvar. Skicka din ansökan i dag.";
var_dump(Post_language::of($english), Post_language::of($danish), Post_language::of('Backend Developer'), Post_language::of($norwegian), Post_language::of($swedish), Post_language::of($bokmal));
echo Post_language::rule($english, 'application'), "\n";
echo Post_language::rule('PHP, Laravel', 'CV'), "\n";
?>
--EXPECT--
string(7) "English"
string(6) "Danish"
NULL
NULL
NULL
NULL
Write the application in English, because the job post is in English, even where the CV, the requirements or the notes are in another language.
Write the CV in the language of the job post, whatever language the CV, the requirements or the notes are in.
