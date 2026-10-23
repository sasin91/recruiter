--TEST--
an entry as its form posted it: months, bullets one per line, a link given its scheme, and errors to print the form again with
--FILE--
<?php
require_once __DIR__ . '/../Profile_page.php';

$read = Profile_fields::entry_from_form([
    'section' => 'project', 'title' => ' Recruiter ', 'url' => 'recruiter.trongate.dev',
    'started' => '2026-10', 'ended' => '', 'bullets' => "Parses job posts\r\n\r\n  Ranks matches  ", 'summary' => '', 'note' => '',
]);
echo json_encode($read, JSON_UNESCAPED_SLASHES), "\n";

$read = Profile_fields::entry_from_form(['section' => 'job', 'title' => '', 'started' => '2024-05', 'ended' => '2023-01']);
echo implode("\n", $read['errors']), "\n";

$form = Profile_page::entry_form(['id' => 7] + Profile_fields::entry_from_form(['section' => 'experience', 'title' => 'Tech Lead', 'organisation' => 'Syncronet', 'started' => '2020-04', 'ended' => '2023-01', 'bullets' => "a\nb"])['entry']);
echo $form['line'], ' | ', $form['started'], ' ', $form['ended'], ' | ', json_encode($form['bullets']), ' | ', $form['key'], ' ', var_export($form['open'], true), "\n";
?>
--EXPECT--
{"entry":{"section":"project","title":"Recruiter","organisation":"","location":"","url":"https://recruiter.trongate.dev","started_on":"2026-10-01","ended_on":null,"summary":"","note":"","bullets":["Parses job posts","Ranks matches"]},"errors":[]}
Pick experience, project or education.
Give it a title, like the job title or the project's name.
It ends before it starts.
Tech Lead · Syncronet, April 2020 – January 2023 | 2020-04 2023-01 | "a\nb" | entry-7 false
