--TEST--
an email application with a subject line or questions is flagged as a trap
--FILE--
<?php
require_once __DIR__ . '/../Apply_instructions.php';
$show = function (string $post) {
    $found = Apply_instructions::read($post);
    echo json_encode($found === null ? null : array_diff_key($found, ['lines' => true]), JSON_UNESCAPED_UNICODE), "\n";
};
// Danish: the subject line is the next sentence.
$show("Send din ansøgning og CV til job@firma.dk. Skriv \"Backend 2026\" i emnefeltet.");
// Danish: marked application.
$show("Mærk din ansøgning \"Udvikler\" og send den til hr@kommune.dk senest 1. november.");
// English: subject and a list of questions.
$show("To apply, email careers@acme.com with the subject 'Purple Elephant' and answer these questions:\n1. What did you build last?\n2. Why us?\n\nWe look forward to hearing from you.");
// Easy Apply refused.
$show("Ansøgninger via LinkedIn Easy Apply tages ikke i betragtning. Send en mail til rekruttering@firma.dk og besvar følgende spørgsmål:\n- Hvorfor os?\n- Hvad er dit yndlingsværktøj?");
// A word to start with, no email.
$show("Please start your cover letter with the word 'banana' so we know you read the whole post.");
// Danish: "med emnet".
$show("Send en mail til job@firma.dk med emnet \"Backend 2026\".");
// A plain email application is shown, but isn't a trap.
$show("Send din ansøgning og CV til job@firma.dk.");
?>
--EXPECT--
{"by_email":true,"email":"job@firma.dk","subject":"Backend 2026","keyword":"","asks":["subject"],"trap":true}
{"by_email":true,"email":"hr@kommune.dk","subject":"Udvikler","keyword":"","asks":["subject"],"trap":true}
{"by_email":true,"email":"careers@acme.com","subject":"Purple Elephant","keyword":"","asks":["subject","questions"],"trap":true}
{"by_email":true,"email":"rekruttering@firma.dk","subject":"","keyword":"","asks":["questions","no_easy_apply"],"trap":true}
{"by_email":false,"email":"","subject":"","keyword":"banana","asks":["keyword","proof"],"trap":true}
{"by_email":true,"email":"job@firma.dk","subject":"Backend 2026","keyword":"","asks":["subject"],"trap":true}
{"by_email":true,"email":"job@firma.dk","subject":"","keyword":"","asks":[],"trap":false}
