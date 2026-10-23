--TEST--
the result view prints a fresh, a free and a saved match without notices, escaped, with its saved id and Reject button
--FILE--
<?php
error_reporting(E_ALL);
set_error_handler(function (int $level, string $message) { throw new ErrorException($message, 0, $level); });
// out() as Trongate's string_service gives it for html.
function out(?string $input): string {
    return htmlspecialchars((string) $input, ENT_QUOTES, 'UTF-8');
}
// Trongate's form helpers, as plain as they print.
function form_open(string $location, array $attributes = []): string {
    return '<form method="post" action="' . $location . '" class="' . ($attributes['class'] ?? '') . '">';
}
function form_hidden(string $name, string $value): string {
    return '<input type="hidden" name="' . $name . '" value="' . $value . '">';
}
function form_submit(string $name, string $value): string {
    return '<button type="submit" name="' . $name . '">' . $value . '</button>';
}
function form_close(): string {
    return '</form>';
}
require __DIR__ . '/../Match_result.php';
require __DIR__ . '/../Match_documents.php';
$answer = [
    'groups' => ['requirements' => [['id' => 'r0', 'text' => 'PHP <8>', 'kind' => 'skill', 'alternatives' => [
        ['id' => 'r1', 'text' => 'PHP 8', 'kind' => 'skill'],
    ]]], 'skills' => [], 'title' => [], 'responsibilities' => []],
    'verdicts' => ['r0' => ['verdict' => 'met', 'by' => 'taxonomy', 'method' => 'exact', 'reason' => 'Named.', 'evidence' => 'PHP "8"']],
    'result' => ['index' => 0.5, 'tag' => 'medium', 'parts' => ['requirements' => ['percentage' => 100, 'value' => 150, 'weight' => 150]]],
    'soft_skills' => [],
    'apply_instructions' => ['email' => 'a@b.dk', 'subject' => '', 'keyword' => 'Fragt', 'asks' => ['keyword'], 'lines' => ['Write "Fragt".'], 'trap' => true],
    'saved_id' => 9,
];
$render = function (array $result) {
    ob_start();
    require __DIR__ . '/../views/result.php';
    return ob_get_clean();
};
$fresh = $render(Match_result::from(['job_title' => 'Dev & Ops'], $answer, true,
    Match_documents::from(9, '', [], ['application' => true, 'resume' => true], $answer['apply_instructions']), '{"job": "x"}'));
foreach (['data-saved-id="9"', 'Dev &amp; Ops', 'PHP &lt;8&gt;', '<q>PHP &quot;8&quot;</q>', 'class="alternatives"', 'Include: <code>Fragt</code>',
        'data-show="met"', 'class="document" data-kind="resume" data-id="9"', 'details class="joblog" data-id="9"', 'id="laya"', 'button type="button" class="apply"', 'action="cv_match/submit_reject/9"', '<summary>Not interested</summary>', 'type="checkbox" name="reasons[]" value="commute"', 'name="note"'] as $part) {
    echo $part, ': ', str_contains($fresh, $part) ? 'yes' : 'NO', "\n";
}
$free = $render(Match_result::from(['job_title' => 'Dev'], ['saved_id' => null, 'apply_instructions' => null] + $answer, false, null)
    + ['cv_found' => Match_result::cv_found(['title' => [], 'skill' => ['PHP'], 'experience_years' => 3])]);
foreach (['data-saved-id=""', 'submit_reject', 'template class="cv-found"', 'id="documents"', 'id="laya"'] as $part) {
    echo 'free ', $part, ': ', str_contains($free, $part) ? 'yes' : 'no', "\n";
}
$saved = $render(Match_result::from_saved([
    'id' => '9', 'job_title' => 'Dev', 'company' => '', 'score' => '0.5', 'tag' => 'medium', 'points' => '1', 'max_points' => '2',
    'created_at' => '1760000000', 'job_url' => 'https://x.dk/?a=1&b=2', 'job_text' => 'Line <1>', 'apply_instructions' => null, 'items' => [],
], true, Match_documents::from(9, '', ['application' => ['text' => 'Hi <you>', 'written_at' => '5']], ['application' => true, 'resume' => false], null)));
foreach (['data-format="datetime"', 'data-url="https://x.dk/?a=1&amp;b=2"', '>Line &lt;1&gt;</template>', 'data-written-at="5"', '>Hi &lt;you&gt;</textarea>', 'class="filter"'] as $part) {
    echo 'saved ', $part, ': ', str_contains($saved, $part) ? 'yes' : 'no', "\n";
}
// A rejected match says so and offers to bring it back.
$rejected = $render(Match_result::from_saved([
    'id' => '9', 'job_title' => 'Dev', 'company' => '', 'score' => '0.5', 'tag' => 'medium', 'points' => '1', 'max_points' => '2',
    'created_at' => '1760000000', 'rejected_at' => '1760000500', 'reject_reasons' => 'commute,salary', 'reject_note' => '1h <each> way', 'job_url' => '', 'job_text' => '', 'apply_instructions' => null, 'items' => [],
], true, Match_documents::from(9, '', [], ['application' => true, 'resume' => false], null)));
foreach (['>Not interested<', '>Bring back</button>', 'name="undo" value="1"', 'rejected this match (Too long commute, Salary (1h &lt;each&gt; way)). It shows only in the Rejected list.'] as $part) {
    echo 'rejected ', $part, ': ', str_contains($rejected, $part) ? 'yes' : 'no', "\n";
}
?>
--EXPECT--
data-saved-id="9": yes
Dev &amp; Ops: yes
PHP &lt;8&gt;: yes
<q>PHP &quot;8&quot;</q>: yes
class="alternatives": yes
Include: <code>Fragt</code>: yes
data-show="met": yes
class="document" data-kind="resume" data-id="9": yes
details class="joblog" data-id="9": yes
id="laya": yes
button type="button" class="apply": yes
action="cv_match/submit_reject/9": yes
<summary>Not interested</summary>: yes
type="checkbox" name="reasons[]" value="commute": yes
name="note": yes
free data-saved-id="": yes
free submit_reject: no
free template class="cv-found": yes
free id="documents": no
free id="laya": no
saved data-format="datetime": yes
saved data-url="https://x.dk/?a=1&amp;b=2": yes
saved >Line &lt;1&gt;</template>: yes
saved data-written-at="5": yes
saved >Hi &lt;you&gt;</textarea>: yes
saved class="filter": no
rejected >Not interested<: no
rejected >Bring back</button>: yes
rejected name="undo" value="1": yes
rejected rejected this match (Too long commute, Salary (1h &lt;each&gt; way)). It shows only in the Rejected list.: yes
