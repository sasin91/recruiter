--TEST--
the joblog panel prints each field to copy, choices with the one to pick and what the AI prompt says, the PDFs and whether it's logged
--FILE--
<?php
error_reporting(E_ALL);
set_error_handler(function (int $level, string $message) { throw new ErrorException($message, 0, $level); });
// out() as Trongate's string_service gives it for html.
function out(?string $input): string {
    return htmlspecialchars((string) $input, ENT_QUOTES, 'UTF-8');
}
require __DIR__ . '/../Joblog_panel.php';
$match = [
    'job_title' => 'Backend-udvikler', 'company' => 'Nordisk "Fragt" A/S', 'job_url' => '',
    'job_text' => "Backend-udvikler\nDeltid, 30 timer om ugen.", 'cv_text' => 'Jonas Hansen', 'created_at' => mktime(12, 0, 0, 10, 19, 2026),
    'score' => 0.8, 'tag' => 'good', 'items' => [], 'application_text' => '', 'resume_text' => null, 'apply_instructions' => null,
    'jobnet_joblog' => ['status' => 'Soegt', 'logged_at' => '1792400000'],
];
$panel = Joblog_panel::from(7, Joblog::read($match));
$fields = array_column(array_merge(...array_column($panel['sections'], 'fields')), null, 'name');
echo json_encode($fields['searchStatus']['options'], JSON_UNESCAPED_UNICODE), "\n";
echo json_encode(array_column($fields['unknownPostalOrCity']['options'], 'prompt', 'value')), ' chosen: ',
    implode(',', array_column(array_filter($fields['unknownPostalOrCity']['options'], fn($o) => $o['chosen']), 'value')), "\n";
var_dump($fields['searchStatus']['choose'], $fields['jobType']['choose'], $fields['hoursPerWeek']['numeric'], $fields['jobNotes']['left'], $panel['missing'], $panel['logged']);

ob_start();
require __DIR__ . '/../views/joblog_panel.php';
$html = ob_get_clean();
foreach (['data-name="name" data-label="Virksomhedens navn"', 'value="Nordisk &quot;Fragt&quot; A/S"', '<button type="button" class="chip chosen" aria-pressed="true" data-value="Soegt" data-prompt="choose &quot;Søgt&quot;">',
        '<span class="chip chosen" data-value="Deltid"', 'inputmode="numeric"', 'class="left">', 'class="joblog-pdf unavailable" data-kind="application" title="Skriv',
        'class="joblog-section joblog-saved"', '<input type="checkbox" checked>', '<time datetime="2026-10-19T'] as $part) {
    echo $part, ': ', str_contains($html, $part) ? 'yes' : 'no', "\n";
}
$logged = Joblog_panel::logged(null);
ob_start();
require __DIR__ . '/../views/joblog_logged.php';
$html = ob_get_clean();
echo 'not logged: ', str_contains($html, ' checked') || str_contains($html, '<time') ? 'no' : 'yes', "\n";
?>
--EXPECT--
[{"value":"IkkeSoegt","text":"Ikke søgt","chosen":false,"prompt":"choose \"Ikke søgt\""},{"value":"Soegt","text":"Søgt","chosen":true,"prompt":"choose \"Søgt\""},{"value":"Samtale","text":"Samtale","chosen":false,"prompt":"choose \"Samtale\""}]
{"yes":"tick it","no":"leave it unticked"} chosen: yes
bool(true)
bool(false)
bool(true)
int(1981)
array(1) {
  [0]=>
  string(61) "Jobansøgning: Skriv ansøgningen først (Write application)."
}
array(2) {
  ["checked"]=>
  bool(true)
  ["at"]=>
  string(20) "2026-10-19T08:53:20Z"
}
data-name="name" data-label="Virksomhedens navn": yes
value="Nordisk &quot;Fragt&quot; A/S": yes
<button type="button" class="chip chosen" aria-pressed="true" data-value="Soegt" data-prompt="choose &quot;Søgt&quot;">: yes
<span class="chip chosen" data-value="Deltid": yes
inputmode="numeric": yes
class="left">: yes
class="joblog-pdf unavailable" data-kind="application" title="Skriv: yes
class="joblog-section joblog-saved": yes
<input type="checkbox" checked>: yes
<time datetime="2026-10-19T: yes
not logged: yes
