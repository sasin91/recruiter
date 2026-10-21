--TEST--
an application is delivered by email (recipient, subject, mailto) or on the employer's form, with the PDFs
--FILE--
<?php
require __DIR__ . '/../Application_delivery.php';
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
$show = function (array $delivery) {
    foreach (['by', 'to', 'subject', 'mailto', 'job_url'] as $key) {
        echo "$key: {$delivery[$key]}\n";
    }
    echo 'body: ', strtok($delivery['body'], "\n") ?: '', "\n";
    echo 'asks: ', implode('; ', $delivery['asks']), "\n";
    foreach ($delivery['files'] as $file) {
        echo "{$file['label']}: {$file['kind']} ", $file['ready'] ? 'ready' : 'missing', "\n";
    }
    echo "--\n";
};
$show(Application_delivery::read($match));

// No address to send it to: applied for on the employer's side, from the post.
$match['apply_instructions'] = null;
$match['application_text'] = '';
$match['resume_text'] = null;
$show(Application_delivery::read($match));

// The subject falls back to the job title.
echo Application_delivery::mailto('job@x.dk', Application_delivery::subject($match, ['email' => 'job@x.dk'])), "\n";
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
