--TEST--
contact lines, apply buttons and mail as a skill are not application instructions
--FILE--
<?php
require_once __DIR__ . '/../Apply_instructions.php';
foreach ([
    "Har du spørgsmål til stillingen, så kontakt Peter på peter@firma.dk eller 12345678.",
    "If you have any questions, email jane@acme.com.",
    "Vil du vide mere, så skriv til hr@firma.dk.",
    "Klik på Søg stillingen for at ansøge. Ansøgningsfrist 1. november.",
    "You know Mailchimp, email marketing and our CV parser.",
    "Du skal svare på mails fra kunder.",
] as $post) {
    var_dump(Apply_instructions::read($post));
}
?>
--EXPECT--
NULL
NULL
NULL
NULL
NULL
NULL
