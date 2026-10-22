--TEST--
a missed required location caps the score below medium and shows as a likely disqualifier
--FILE--
<?php
require __DIR__ . '/setup.inc';
function out(?string $input): string {
    return htmlspecialchars((string) $input, ENT_QUOTES, 'UTF-8');
}
require_once __DIR__ . '/../Match_result.php';
$job = [
    'job_title' => 'Backend developer',
    'job_title_en' => 'Backend developer',
    'company' => '',
    'requirements' => [
        $req('PHP'),
        $req('Located close to Copenhagen', ['kind' => 'location']),
        $req('Lives near Odense', ['kind' => 'location', 'required' => false]),
    ],
    'responsibilities' => [],
];
$groups = Cv_matcher::criteria($job);
// The required location is its own group; the nice-to-have one a relevant skill.
echo implode(',', array_keys($groups)), ' | ', $groups['location'][0]['id'], ' | ', $groups['skills'][0]['kind'], "\n";
var_dump($matcher->decide($groups['location'][0]));

$met = ['r0' => ['verdict' => 'met'], 'r2' => ['verdict' => 'met'], 't0' => ['verdict' => 'met']];
$far = ['verdict' => 'missing', 'by' => 'llm', 'reason' => 'Slagelse is about 100 km from Copenhagen.', 'evidence' => ''];
$fine = Cv_matcher::score($groups, $met + ['l1' => ['verdict' => 'met']]);
$capped = Cv_matcher::score($groups, $met + ['l1' => $far]);
// Not weighed: the same points either way, but the miss caps the index.
echo $fine['rank'], '/', $fine['total'], ' ', $fine['tag'], ' | ', $capped['rank'], '/', $capped['total'], ' ', $capped['index'], ' ', $capped['tag'], "\n";
echo implode(',', array_keys($capped['parts'])), "\n";

$notice = Location_notice::from($groups, $met + ['l1' => $far]);
echo $notice['lines'][0]['text'], ' · ', $notice['lines'][0]['reason'], "\n";
var_dump(Location_notice::from($groups, $met + ['l1' => ['verdict' => 'partial']]));
$saved = Location_notice::from_saved([
    ['criterion' => 'requirements', 'text' => 'PHP', 'verdict' => 'missing', 'reason' => ''],
    ['criterion' => 'location', 'text' => 'Located close to Copenhagen', 'verdict' => 'missing', 'reason' => 'Too far.'],
]);
echo $saved['lines'][0]['text'], ' · ', $saved['lines'][0]['reason'], "\n";

$answer = ['groups' => $groups, 'verdicts' => $met + ['l1' => $far], 'result' => $capped, 'soft_skills' => [], 'apply_instructions' => null, 'saved_id' => null];
$result = Match_result::from($job, $answer, false, null);
ob_start();
require __DIR__ . '/../views/result_body.php';
$html = ob_get_clean();
foreach (['class="location-notice"', Location_notice::HEADING, Match_result::LOCATION_NOTE] as $part) {
    echo $part, ': ', str_contains($html, $part) ? 'yes' : 'NO', "\n";
}
?>
--EXPECT--
requirements,skills,title,responsibilities,location | l1 | location
NULL
230/230 top | 230/230 0.59 poor
requirements,skills,title
Located close to Copenhagen · Slagelse is about 100 km from Copenhagen.
NULL
Located close to Copenhagen · Too far.
class="location-notice": yes
Likely disqualifier: where you live: yes
not weighed · a miss caps the score: yes
