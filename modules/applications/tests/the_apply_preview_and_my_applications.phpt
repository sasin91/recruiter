--TEST--
the apply preview names each verdict for the candidate; My applications rows
--FILE--
<?php
require_once __DIR__ . '/../Match_preview.php';
require_once __DIR__ . '/../Sent_application_row.php';
$groups = ['requirements' => [['id' => 'r1', 'text' => 'PHP'], ['id' => 'r2', 'text' => 'SQL']], 'skills' => []];
$preview = Match_preview::from($groups, ['r1' => ['verdict' => 'met'], 'r2' => ['verdict' => 'partial']], ['index' => 0.7, 'tag' => 'medium']);
echo $preview['score']['percent'], ' ', $preview['score']['tag_name'], "\n";
foreach ($preview['lists'] as $list) {
    echo $list['heading'], ': ', implode(', ', array_map(fn($i) => "{$i['text']} ({$i['verdict']}: {$i['verdict_name']})", $list['items'])), "\n";
}
$a = ['id' => '3', 'title' => 'Kok', 'company_name' => 'Kroen', 'public_token' => 'abc', 'status' => 'in_review', 'submitted_at' => (string) mktime(9, 0, 0, 10, 20, 2026), 'post_status' => 'active'];
$row = Sent_application_row::from($a);
echo "{$row['post_url']} {$row['status_name']} {$row['status_class']} {$row['sent']}\n";
var_dump($row['can_withdraw'], $row['apply_again_url']);
$withdrawn = Sent_application_row::from(['status' => 'withdrawn'] + $a);
var_dump($withdrawn['status_name'], $withdrawn['can_withdraw'], $withdrawn['apply_again_url']);
var_dump(Sent_application_row::from(['status' => 'withdrawn', 'post_status' => 'closed'] + $a)['apply_again_url']);
?>
--EXPECT--
70 Medium match
What they ask for: PHP (met: In your CV), SQL (partial: Something close)
jobs/abc Sent active 20 Oct 2026
bool(true)
NULL
string(9) "Withdrawn"
bool(false)
string(14) "jobs/abc/apply"
NULL
