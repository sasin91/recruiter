--TEST--
a saved match as an email to apply with: recipient, subject, mailto link and the PDFs to attach
--FILE--
<?php
require __DIR__ . '/../Apply_email.php';
$match = [
    'job_title' => 'Backend-udvikler',
    'company' => 'Nordisk Fragt A/S',
    'job_text' => "Backend-udvikler\nSend din ansøgning til job@nordiskfragt.dk med emnet \"Backend & drift\".\nHar du spørgsmål, så kontakt Mette Hansen på mette@nordiskfragt.dk.",
    'cv_text' => 'Jonas Hansen',
    'application_text' => "Kære Nordisk Fragt\n\nJeg søger...",
    'resume_text' => 'Jonas Hansen, tailored',
    'apply_instructions' => ['email' => 'job@nordiskfragt.dk', 'subject' => 'Backend & drift', 'asks' => ['subject', 'questions']],
];
$email = Apply_email::read($match);
foreach (['to', 'to_note', 'subject', 'mailto'] as $key) {
    echo "$key: {$email[$key]}\n";
}
echo 'body: ', strtok($email['body'], "\n"), "\n";
echo 'asks: ', implode('; ', $email['asks']), "\n";
foreach ($email['files'] as $file) {
    echo "{$file['label']}: {$file['kind']} ", $file['ready'] ? 'ready' : 'missing', "\n";
}

// Without apply instructions: the contact person's email and the job title.
$match['apply_instructions'] = null;
$match['application_text'] = '';
$match['resume_text'] = null;
$email = Apply_email::read($match);
foreach (['to', 'to_note', 'subject', 'mailto'] as $key) {
    echo "$key: {$email[$key]}\n";
}
foreach ($email['files'] as $file) {
    echo "{$file['label']}: {$file['kind']} ", $file['ready'] ? 'ready' : 'missing', "\n";
}

// No address anywhere: no mailto link.
$match['job_text'] = 'Backend-udvikler';
$email = Apply_email::read($match);
var_export([$email['to'], $email['mailto'], $email['to_note']]);
echo "\n";
?>
--EXPECT--
to: job@nordiskfragt.dk
to_note: The address the post asks applications to be sent to.
subject: Backend & drift
mailto: mailto:job@nordiskfragt.dk?subject=Backend%20%26%20drift
body: Kære Nordisk Fragt
asks: answers to questions
Application: application ready
Tailored résumé: resume ready
to: mette@nordiskfragt.dk
to_note: The contact person in the post: the post doesn't say where to send applications.
subject: Backend-udvikler
mailto: mailto:mette@nordiskfragt.dk?subject=Backend-udvikler
Application: application missing
CV: cv ready
array (
  0 => '',
  1 => '',
  2 => 'The post names no email address: look for one in the post.',
)
