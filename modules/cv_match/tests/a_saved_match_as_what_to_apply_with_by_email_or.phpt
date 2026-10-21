--TEST--
a saved match as what to apply with: by email (recipient, subject, mailto) or on the employer's form, with the PDFs
--FILE--
<?php
require __DIR__ . '/../Apply_kit.php';
$match = [
    'job_title' => 'Backend-udvikler',
    'company' => 'Nordisk Fragt A/S',
    'job_url' => 'https://example.dk/job/1',
    'job_text' => "Backend-udvikler\nSend din ansøgning til job@nordiskfragt.dk med emnet \"Backend & drift\".",
    'cv_text' => 'Jonas Hansen',
    'application_text' => "Kære Nordisk Fragt\n\nJeg søger...",
    'resume_text' => 'Jonas Hansen, tailored',
    'apply_instructions' => ['email' => 'job@nordiskfragt.dk', 'subject' => 'Backend & drift', 'asks' => ['subject', 'questions']],
];
$show = function (array $kit) {
    foreach (['by', 'to', 'subject', 'mailto', 'job_url'] as $key) {
        echo "$key: {$kit[$key]}\n";
    }
    echo 'body: ', strtok($kit['body'], "\n") ?: '', "\n";
    echo 'asks: ', implode('; ', $kit['asks']), "\n";
    foreach ($kit['files'] as $file) {
        echo "{$file['label']}: {$file['kind']} ", $file['ready'] ? 'ready' : 'missing', "\n";
    }
    echo "--\n";
};
$show(Apply_kit::read($match));

// No address to send it to: applied for on the employer's side, from the post.
$match['apply_instructions'] = null;
$match['application_text'] = '';
$match['resume_text'] = null;
$show(Apply_kit::read($match));

// The subject falls back to the job title.
echo Apply_kit::mailto('job@x.dk', Apply_kit::subject($match, ['email' => 'job@x.dk'])), "\n";
?>
--EXPECT--
by: email
to: job@nordiskfragt.dk
subject: Backend & drift
mailto: mailto:job@nordiskfragt.dk?subject=Backend%20%26%20drift
job_url: https://example.dk/job/1
body: Kære Nordisk Fragt
asks: answers to questions
Application: application ready
Tailored résumé: resume ready
--
by: form
to: 
subject: Backend-udvikler
mailto: 
job_url: https://example.dk/job/1
body: 
asks: 
Application: application missing
CV: cv ready
--
mailto:job@x.dk?subject=Backend-udvikler
