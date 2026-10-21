--TEST--
an application becomes a Matchmaker card: score, Laya, scoring state, chips, buttons
--FILE--
<?php
require_once __DIR__ . '/../../queue/Job.php';
require_once __DIR__ . '/../Applicant_card.php';
$groups = ['requirements' => [['id' => 'r1', 'text' => 'PHP'], ['id' => 'r2', 'text' => 'SQL']], 'skills' => [['id' => 's1', 'text' => 'Docker']]];
$list = ['post_id' => 9, 'groups' => $groups, 'laya_on' => true, 'fields' => ['tab' => 'all', 'tag' => '', 'required' => '', 'after' => '', 'since' => '1000']];
$a = ['id' => '5', 'status' => 'in_review', 'combined_score' => '0.82', 'tag' => 'good', 'final_rank' => '2', 'name' => 'Ann', 'is_new' => true,
    'current_title' => 'Barista', 'submitted_at' => (string) mktime(9, 0, 0, 10, 20, 2026), 'shortlisted_at' => null, 'bookmarked_at' => '1',
    'laya_meets' => '0.82', 'laya_choice' => 'fit 3', 'needs_human' => '1', 'current' => true, 'score_id' => '1', 'score_version' => '2', 'scoring' => null,
    'details' => ['r1' => ['rank' => '1', 'reason' => 'Named', 'stage' => 'taxonomy'], 'r2' => ['rank' => '0.5', 'reason' => 'Close', 'stage' => 'llm'], 's1' => ['rank' => '0', 'reason' => 'No', 'stage' => 'none']],
    'cover_letter' => 'Hi', 'email' => 'ann@example.com', 'phone' => '', 'raw_text' => 'CV'];

$card = Applicant_card::from($a, $list);
echo $card['score']['percent'], ' #', $card['rank'], ' ', $card['meta'], ' · ', $card['tag_name'], ' · ', implode(', ', $card['flags']), "\n";
echo $card['laya']['text'], $card['laya']['unsure'] ? ' (unsure)' : '', "\n";
foreach ($card['chips'] as $group) {
    echo $group['heading'], ': ', implode(', ', array_map(fn($c) => "{$c['text']} {$c['verdict_name']}", $group['chips'])), "\n";
}
echo implode('; ', array_map(fn($r) => "{$r['item']}: {$r['reason']} ({$r['judged_by']})", $card['cv']['reasons'])), "\n";
echo implode(' | ', array_map(fn($b) => "{$b['label']} [{$b['class']}] {$b['fields']['action']}", $card['buttons'])), "\n";
echo $card['buttons'][0]['url'], ' ', http_build_query($card['buttons'][0]['fields']), "\n";

$failed = Applicant_card::from(['current' => false, 'score_id' => null, 'laya_meets' => null, 'shortlisted_at' => '1',
    'scoring' => new Job('applications', '_score', attempts: 3, failed_at: 5, error_message: 'Timeout.')] + $a, $list);
echo $failed['scoring']['text'], $failed['scoring']['warn'] ? ' (warn)' : '', "\n";
var_dump($failed['chips'], $failed['not_current'], $failed['laya'], $failed['laya_missing']);
echo implode(' | ', array_map(fn($b) => $b['label'], $failed['buttons'])), "\n";

$old = Applicant_card::from(['current' => false, 'score_version' => '1', 'status' => 'rejected', 'final_rank' => null, 'bookmarked_at' => null,
    'scoring' => new Job('applications', '_score', attempts: 1, reserved_at: 5, error_message: 'Oops')] + $a, $list);
echo $old['scoring']['text'], "\n", $old['not_current'], "\n", implode(' | ', array_map(fn($b) => $b['label'], $old['buttons'])), "\n";
var_dump($old['rank'], $old['rejected']);
?>
--EXPECT--
82 #2 Barista · Applied 20 Oct · Good match · Bookmarked
Laya: likely meets every requirement (82%), fit 3. (unsure)
Requirements: PHP met, SQL partly met
Nice to have: Docker missing
PHP: Named (taxonomy); SQL: Close (AI); Docker: No (not judged)
Shortlist [button small primary] shortlist | Remove bookmark [button small] unbookmark | Reject [button small] reject
matchmaker/submit_action/9/5 action=shortlist&tab=all&tag=&required=&after=&since=1000
Scoring failed after 3 tries: Timeout. Re-score to try again. (warn)
array(0) {
}
string(0) ""
NULL
bool(false)
Remove from shortlist | Remove bookmark | Reject | Re-score
Being scored now. The last try failed (Oops), so it will try again.
Scored on version 1 of the post. Re-score to match it against the requirements as they are now.
Undo reject
NULL
bool(true)
