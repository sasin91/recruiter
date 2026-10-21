--TEST--
an application is delivered by email (recipient, subject, mailto) or on the employer's form, with the PDFs
--FILE--
<?php
require __DIR__ . '/../Application_delivery.php';
$match = [
    'id' => 7,
    'job_title' => 'Backend-udvikler',
    'job_url' => 'https://example.dk/job/1',
    'cv_text' => 'Jonas Hansen',
    'application_text' => "Kære Nordisk Fragt\n\nJeg søger...",
    'resume_text' => 'Jonas Hansen, tailored',
    'apply_instructions' => ['email' => 'job@nordiskfragt.dk', 'subject' => 'Backend & drift', 'asks' => ['subject', 'questions']],
];
$show = function (array $delivery) {
    echo "{$delivery['by']}: {$delivery['heading']}\n";
    echo 'intro: ', $delivery['intro'], "\n";
    foreach ($delivery['fields'] as $field) {
        echo "{$field['id']} {$field['label']}: ", strtok($field['value'], "\n") ?: '', $field['hint'] !== '' ? " ({$field['hint']})" : '', $field['multiline'] ? ' [multiline]' : '', "\n";
    }
    echo 'asks: ', $delivery['asks'], "\n";
    echo $delivery['files_heading'], ': ';
    echo implode(', ', array_map(fn($f) => "{$f['label']} {$f['kind']} " . ($f['ready'] ? 'ready' : 'missing'), $delivery['files'])), "\n";
    echo 'missing: ', implode(' | ', $delivery['missing']), "\n";
    echo 'open: ', $delivery['open'] ? "{$delivery['open']['label']} {$delivery['open']['href']} {$delivery['open']['target']}" : '-', "\n--\n";
};
$show(Application_delivery::from($match));

// No address to send it to: applied for on the employer's side.
$match['apply_instructions'] = null;
$match['application_text'] = '';
$match['resume_text'] = null;
$show(Application_delivery::from($match));

// Without a link to the post either.
$match['job_url'] = '';
$show(Application_delivery::from($match));

// The subject falls back to the job title.
echo Application_delivery::mailto('job@x.dk', Application_delivery::subject(['job_title' => 'Backend-udvikler'])), "\n";
?>
--EXPECT--
email: Apply by email
intro: 
delivery-to To: job@nordiskfragt.dk (The address the post asks applications to be sent to.)
delivery-subject Subject: Backend & drift
delivery-body Body: Kære Nordisk Fragt [multiline]
asks: The post also asks for answers to questions.
Files to attach: Application application ready, Tailored résumé resume ready
missing: 
open: Open in your email app mailto:job@nordiskfragt.dk?subject=Backend%20%26%20drift _self
--
form: Apply on the employer's site
intro: The post doesn't ask for an email, so apply where it says: its application form or Easy Apply. Paste the application into the cover letter field and upload the PDFs.
delivery-body Application:  (Write the application first (Write application).) [multiline]
asks: 
Files to upload: Application application missing, CV cv ready
missing: Application: Write the application first (Write application).
open: Open the job post https://example.dk/job/1 _blank
--
form: Apply on the employer's site
intro: The post doesn't ask for an email, so apply where it says: its application form or Easy Apply. Paste the application into the cover letter field and upload the PDFs. This match has no link to the post: open it where you found it.
delivery-body Application:  (Write the application first (Write application).) [multiline]
asks: 
Files to upload: Application application missing, CV cv ready
missing: Application: Write the application first (Write application).
open: -
--
mailto:job@x.dk?subject=Backend-udvikler
