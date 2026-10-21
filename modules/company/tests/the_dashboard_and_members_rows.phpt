--TEST--
the company dashboard picks a tab and counts posts; members rows offer an owner's changes
--FILE--
<?php
define('BASE_URL', 'https://recruiter.test/');
require_once __DIR__ . '/../Company_dashboard.php';
require_once __DIR__ . '/../Member_row.php';
$at = (string) mktime(9, 0, 0, 10, 20, 2026);
$post = fn($id, $status, $apps = 0, $new = 0, $short = 0) => ['id' => $id, 'title' => "Post $id", 'status' => $status, 'published_at' => $status === 'draft' ? null : $at,
    'created_at' => $at, 'applications' => $apps, 'new' => $new, 'shortlisted' => $short];
$posts = [$post(1, 'active', 4, 2, 1), $post(2, 'paused', 3), $post(3, 'draft', 0, 1), $post(4, 'archived', 9)];
$m = fn($id, $role, $active = 1, $pending = 0, $last = null) => ['id' => $id, 'name' => "M$id", 'email' => "m$id@x", 'role' => $role, 'active' => $active,
    'invite_pending' => $pending, 'invite_token' => $pending ? 'tok' : null, 'invited_at' => $at, 'last_login' => $last];
$members = [$m(1, 'owner', 1, 0, $at), $m(2, 'recruiter'), $m(3, 'owner'), $m(4, 'recruiter', 0), $m(5, 'recruiter', 1, 1)];
$me = ['id' => 1, 'name' => 'Jonas Kerwin Hansen', 'role' => 'owner'];

$d = Company_dashboard::from($me, $members, $posts, null);
echo $d['greeting'], ' · ', $d['tab'], ' · ', $d['members_line'], "\n";
echo implode(' ', array_map(fn($t) => $t['name'] . '=' . $t['count'] . ($t['current'] ? '*' : ''), $d['tabs'])), "\n";
echo json_encode($d['totals']), "\n";
echo implode(' | ', array_map(fn($p) => "{$p['title']} {$p['status_name']} {$p['dated']} {$p['review_label']}" . ($p['has_candidates'] ? ' +candidates' : ''), $d['posts'])), "\n";
echo Company_dashboard::from($me, $members, [$post(3, 'draft')], null)['tab'], ' ', Company_dashboard::from($me, $members, [$post(3, 'draft')], 'active')['tab'], ' ', Company_dashboard::from($me, $members, $posts, 'odd')['tab'], "\n";
echo implode(' | ', array_map(fn($p) => "{$p['title']} {$p['dated']} {$p['review_label']}", Company_dashboard::from($me, $members, $posts, 'drafts')['posts'])), "\n";

foreach ($members as $member) {
    $row = Member_row::from($member, 1, true);
    echo $row['name'], $row['is_me'] ? ' (you)' : '', " {$row['role_name']} · {$row['status']} · ", implode(', ', array_map(fn($b) => "{$b['label']}:{$b['fields']['change']}", $row['buttons'])), $row['invite_url'] ? " · {$row['invite_url']}" : '', "\n";
}
var_dump(Member_row::from($members[4], 2, false)['buttons'], Member_row::from($members[4], 2, false)['invite_url']);
?>
--EXPECT--
Hi Jonas · active · 3 active, 1 invited.
Live=2* Drafts=1 Closed=1
{"live":1,"applications":7,"new":3,"shortlisted":1}
Post 1 Live Published 20 Oct 2026 Edit +candidates | Post 2 Paused Published 20 Oct 2026 Edit +candidates
drafts active active
Post 3 Created 20 Oct 2026 Review
M1 (you) Owner · Last signed in 20 Oct · 
M2 Recruiter · Active · Make owner:make_owner, Deactivate:deactivate
M3 Owner · Active · Make recruiter:make_recruiter, Deactivate:deactivate
M4 Recruiter · Deactivated · Reactivate:activate
M5 Recruiter · Invited 20 Oct · Withdraw invite:revoke · https://recruiter.test/company/join/tok
array(0) {
}
NULL
