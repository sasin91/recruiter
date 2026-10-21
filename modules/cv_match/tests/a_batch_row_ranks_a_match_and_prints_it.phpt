--TEST--
a batch row is a match's score, job, host, when it was matched before and what the post asks, printed over its result
--FILE--
<?php
error_reporting(E_ALL);
set_error_handler(function (int $level, string $message) { throw new ErrorException($message, 0, $level); });
// out() as Trongate's string_service gives it for html.
function out(?string $input): string {
    return htmlspecialchars((string) $input, ENT_QUOTES, 'UTF-8');
}
require __DIR__ . '/../Match_result.php';
require __DIR__ . '/../Match_documents.php';
require __DIR__ . '/../Batch_row.php';
$answer = [
    'groups' => ['requirements' => [['id' => 'r0', 'text' => 'PHP', 'kind' => 'skill']], 'skills' => [], 'title' => [], 'responsibilities' => []],
    'verdicts' => ['r0' => ['verdict' => 'met', 'by' => 'taxonomy', 'method' => 'exact', 'reason' => 'Named.', 'evidence' => '']],
    'result' => ['index' => 0.81, 'tag' => 'good', 'parts' => ['requirements' => ['percentage' => 81, 'value' => 81, 'weight' => 100]]],
    'soft_skills' => ['Calm'],
    'apply_instructions' => ['email' => 'a@b.dk', 'subject' => '', 'keyword' => '', 'asks' => [], 'lines' => ['Send it to a@b.dk.'], 'trap' => false],
    'saved_id' => 9,
];
$fresh = Match_result::from(['job_title' => '', 'heading' => ''], $answer, true,
    Match_documents::from(9, '', [], ['application' => true, 'resume' => true], $answer['apply_instructions']));
$row = Batch_row::from($fresh, 'https://www.jobindex.dk/job/1?a=1');
var_dump($row['percent'], $row['heading'], $row['host'], $row['matched_at'], $row['apply_chip'], $row['line']);

$saved = Match_result::from_saved([
    'id' => '4', 'job_title' => 'Dev <ops>', 'company' => 'Acme', 'score' => '0.62', 'tag' => 'medium', 'points' => '5', 'max_points' => '8',
    'created_at' => '1760000000', 'job_url' => '', 'job_text' => 'x', 'apply_instructions' => null, 'items' => [],
], true, Match_documents::from(null, 'The database said no.', [], [], null));
$again = Batch_row::from($saved, 'not a url');
var_dump($again['heading'], $again['host'], $again['matched_at'], $again['apply_chip'], $again['line']);

$render = function (array $result, array $row) {
    ob_start();
    require __DIR__ . '/../views/batch_row.php';
    return ob_get_clean();
};
$html = $render($fresh, $row);
foreach (['data-score="81"', 'data-saved-id="9"', 'jobindex.dk', 'class="apply-chip">Apply by email', 'class="soft-skills"',
        'class="batch-documents"', 'class="document" data-kind="application" data-id="9"', 'data-show="met"', 'matched before'] as $part) {
    echo $part, ': ', str_contains($html, $part) ? 'yes' : 'no', "\n";
}
$html = $render($saved, $again);
foreach (['Dev &lt;ops&gt; · Acme', 'matched before, <time datetime="2025-10-09T08:53:20Z">', '<p class="error">The database said no.</p>'] as $part) {
    echo 'saved ', $part, ': ', str_contains($html, $part) ? 'yes' : 'no', "\n";
}
?>
--EXPECT--
int(81)
string(8) "Job post"
string(15) "www.jobindex.dk"
string(0) ""
array(2) {
  ["label"]=>
  string(14) "Apply by email"
  ["trap"]=>
  bool(false)
}
string(25) "Requirements 81% (81/100)"
string(17) "Dev <ops> · Acme"
string(0) ""
string(20) "2025-10-09T08:53:20Z"
NULL
string(31) "5/8 points · not matched again"
data-score="81": yes
data-saved-id="9": yes
jobindex.dk: yes
class="apply-chip">Apply by email: yes
class="soft-skills": yes
class="batch-documents": yes
class="document" data-kind="application" data-id="9": yes
data-show="met": yes
matched before: no
saved Dev &lt;ops&gt; · Acme: yes
saved matched before, <time datetime="2025-10-09T08:53:20Z">: yes
saved <p class="error">The database said no.</p>: yes
