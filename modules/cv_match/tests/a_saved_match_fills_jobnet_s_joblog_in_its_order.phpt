--TEST--
a saved match fills Jobnet's joblog in its order, with notes and the PDFs to upload
--FILE--
<?php
require __DIR__ . '/../Joblog.php';
$match = [
    'job_title' => 'Backend-udvikler',
    'company' => 'Nordisk Fragt A/S',
    'job_url' => 'https://example.dk/job/1',
    'job_text' => "Backend-udvikler\nNordisk Fragt A/S\nHavnegade 4, 5000 Odense C\n\nDeltid, 30 timer om ugen.\nAnsøgningsfrist: 1. november 2026.",
    'cv_text' => 'Jonas Hansen',
    'created_at' => mktime(12, 0, 0, 10, 19, 2026),
    'score' => 0.8234,
    'tag' => 'good',
    'items' => [
        ['criterion' => 'title', 'text' => 'Backend-udvikler', 'verdict' => 'met'],
        ['criterion' => 'requirements', 'text' => 'PHP', 'verdict' => 'met'],
        ['criterion' => 'requirements', 'text' => 'Go', 'verdict' => 'partial'],
        ['criterion' => 'requirements', 'text' => 'Kubernetes', 'verdict' => 'missing'],
    ],
    'application_text' => 'Kære Nordisk Fragt',
    'resume_text' => null,
    'apply_instructions' => ['email' => 'job@nordiskfragt.dk', 'subject' => 'Backend'],
];
$entry = Joblog::read($match);
foreach ($entry['sections'] as $section) {
    echo "# {$section['heading']}\n";
    foreach ($section['fields'] as $field) {
        echo "{$field['name']}: ", var_export($field['value'], true), "\n";
    }
}
foreach ($entry['files'] as $file) {
    echo "{$file['label']}: {$file['kind']} ", $file['ready'] ? 'ready' : 'missing', "\n";
}
?>
--EXPECT--
# Om jobbet
occupation: 'Backend-udvikler'
submissionDeadline: '01-11-2026'
jobType: 'Deltid'
hoursPerWeek: '30'
# Om arbejdspladsen
name: 'Nordisk Fragt A/S'
companyAddress: 'Havnegade 4'
companyCountry: 'Danmark'
unknownPostalOrCity: false
postalCodeAndCity: '5000 Odense C'
companyContactName: ''
companyContactPhone: ''
companyContactEmail: 'job@nordiskfragt.dk'
jobAdLink: 'https://example.dk/job/1'
# Om din jobsøgning
searchStatus: 'Soegt'
applicationFormTypeFound: 'OpslaaetStilling'
applicationFormTypeApplied: 'Digitalt'
jobNotes: 'Match: 82 % (good).
Opfylder: PHP, Go.
Mangler: Kubernetes.
Ansøgningen sendes til job@nordiskfragt.dk med emnet "Backend".'
Jobansøgning: application ready
CV: cv ready
Jobannonce: job ready
