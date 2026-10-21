--TEST--
the public page shows requirements as tags: required first, an OR-group as one tag
--FILE--
<?php
require_once __DIR__ . '/../Public_job_post.php';
$r = fn($kind, $text, $required = 1, $group = null, $years = null, $level = null) =>
    ['kind' => $kind, 'raw_text' => $text, 'english' => '', 'is_required' => $required, 'alt_group' => $group, 'min_years' => $years, 'min_level' => $level];
$rows = [
    $r('title', 'Kok'), $r('responsibility_area', 'Frokost til 200'),
    $r('education', 'Uddannet kok', 1, 1), $r('experience', 'køkken', 1, 1, 5), $r('language', 'Engelsk', 1, null, null, 'fluent'),
    $r('skill', 'Bagning', 0), $r('experience', '3 år som kok', 0, 2, 3), $r('skill', 'Is', 0, 2),
    $r('soft_skill', 'Glad', 1, 3), $r('soft_skill', 'Rolig', 1, 3),
];
$post = ['status' => 'paused', 'work_hours' => 'full_time', 'workplace_flexibility' => '', 'postal_code' => '8000'];
$page = Public_job_post::from($post, $rows);
echo implode(' | ', $page['facts']), "\n", $page['notice'], "\n", implode(' | ', $page['responsibilities']), "\n";
var_dump($page['takes_applications']);
foreach ($page['lists'] as $list) {
    echo $list['title'], $list['required'] ? ' (required)' : '', ': ';
    echo implode(' | ', array_map(fn($tag) => implode(' or ', array_map(fn($alt) => trim(implode(' ', $alt)), $tag)), $list['tags'])), "\n";
}
echo Public_job_post::from(['status' => 'active'] + $post, [])['notice'] === '' ? 'active: no notice' : 'notice!', "\n";
?>
--EXPECT--
Full time | Postal code 8000
This job isn't taking applications right now.
Frokost til 200
bool(false)
What you bring (required): Uddannet kok or køkken 5+ yrs | Engelsk  (fluent)
Nice to have: Bagning | 3 år som kok or Is
Who you are: Glad | Rolig
active: no notice
