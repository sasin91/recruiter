--TEST--
a match result as the page prints it, fresh or saved: score, parts, verdicts missing first, filter counts, apply notice, documents
--FILE--
<?php
require __DIR__ . '/../Match_result.php';
require __DIR__ . '/../Match_documents.php';
$groups = [
    'requirements' => [
        ['id' => 'r0', 'text' => 'PHP', 'kind' => 'skill'],
        ['id' => 'g0', 'text' => 'Go / Rust', 'kind' => 'alternatives', 'alternatives' => [
            ['id' => 'r1', 'text' => 'Go', 'kind' => 'skill'],
            ['id' => 'r2', 'text' => 'Rust', 'kind' => 'skill'],
        ]],
    ],
    'skills' => [],
    'title' => [['id' => 't0', 'text' => 'Backend-udvikler', 'kind' => 'title']],
    'responsibilities' => [],
];
$answer = [
    'groups' => $groups,
    'verdicts' => (object) [
        'r0' => ['verdict' => 'met', 'by' => 'taxonomy', 'method' => 'exact', 'reason' => 'Named in the CV.', 'evidence' => 'PHP'],
        'r1' => ['verdict' => 'missing', 'by' => 'taxonomy', 'method' => 'not_found', 'reason' => 'Not named.', 'evidence' => ''],
        'r2' => ['verdict' => 'partial', 'by' => 'llm', 'method' => 'judgement', 'reason' => 'Some systems work.', 'evidence' => ''],
        'g0' => ['verdict' => 'partial', 'by' => 'llm', 'method' => 'judgement', 'reason' => 'Best of Go or Rust.', 'evidence' => ''],
    ],
    'result' => ['index' => 0.814, 'tag' => 'good', 'parts' => [
        'requirements' => ['percentage' => 75, 'value' => 112.5, 'weight' => 150],
        'skills' => ['percentage' => 0, 'value' => 0, 'weight' => 50],
        'title' => ['percentage' => 0, 'value' => 0, 'weight' => 30],
        'responsibilities' => ['percentage' => 0, 'value' => 0, 'weight' => 30],
    ]],
    'soft_skills' => ['Team player'],
    'apply_instructions' => ['email' => 'job@nordiskfragt.dk', 'subject' => 'Backend', 'keyword' => '', 'asks' => ['subject', 'questions'], 'lines' => ['Send it to job@nordiskfragt.dk with Backend as the subject.'], 'trap' => true],
    'laya' => ['meets' => 0.42, 'fit' => 2.25, 'fit_label' => 'fair'],
    'saved_id' => 7,
];
$documents = Match_documents::from(7, '', [], ['application' => true, 'resume' => false], $answer['apply_instructions']);
$show = function (array $r) {
    echo "{$r['score']['percent']}% {$r['score']['tag']} | {$r['heading']} | {$r['parts']}", $r['saved_at'] !== '' ? " · saved {$r['saved_at']}" : '', "\n";
    echo 'filters: ', implode(', ', array_map(fn($f) => "{$f['label']} {$f['count']}", $r['filters'])), "\n";
    foreach ($r['sections'] as $section) {
        echo "## {$section['heading']}", $section['note'] !== '' ? " ({$section['note']})" : '', "\n";
        foreach ($section['rows'] as $row) {
            echo "- {$row['verdict']} {$row['text']}: {$row['reason']} [{$row['decided_by']}]\n";
            foreach ($row['alternatives'] as $a) {
                echo "  - {$a['verdict']} {$a['text']} [{$a['decided_by']}]\n";
            }
        }
    }
    echo 'soft: ', implode(', ', $r['soft_skills']), "\n";
    echo 'apply: ', $r['apply'] ? "{$r['apply']['heading']} | {$r['apply']['note']} | {$r['apply']['mailto']} | {$r['apply']['asks']}" : '-', "\n";
    echo 'laya: ', $r['laya']['text'], "\n";
    if ($r['documents']) {
        foreach ($r['documents']['boxes'] as $box) {
            echo "box {$box['kind']}: {$box['title']} / {$box['write']}", $box['off'] ? " / {$box['off_note']}" : '', $box['send_to'] ? " / to {$box['send_to']['email']}" : '', "\n";
        }
    }
    echo "--\n";
};
$show(Match_result::from(['job_title' => 'Backend-udvikler', 'company' => 'Nordisk Fragt'], $answer, true, $documents));

// The free match: no documents, a heading when the post has no title, Laya failed.
$free = ['saved_id' => null, 'apply_instructions' => null, 'laya' => null, 'laya_error' => 'Laya is down.'] + $answer;
$show(Match_result::from(['job_title' => '', 'heading' => 'Udvikler søges', 'company' => ''], $free, false, null));

// A saved match.
$saved = [
    'id' => '7', 'job_title' => 'Backend-udvikler', 'company' => 'Nordisk Fragt', 'score' => '0.814', 'tag' => 'good',
    'points' => '112.5', 'max_points' => '260', 'created_at' => (string) gmmktime(9, 30, 0, 10, 21, 2026),
    'job_url' => 'https://example.dk/job/1', 'job_text' => 'Backend-udvikler...', 'apply_instructions' => null,
    'application_text' => 'Kære Nordisk Fragt', 'application_written_at' => '1760000000', 'resume_text' => null, 'resume_written_at' => null, 'resume_structured' => '0',
    'items' => [
        ['criterion' => 'requirements', 'text' => 'PHP', 'verdict' => 'met', 'decided_by' => 'exact', 'reason' => 'Named.', 'evidence' => 'PHP'],
        ['criterion' => 'requirements', 'text' => '5 years', 'verdict' => 'missing', 'decided_by' => 'years', 'reason' => 'Has 3.', 'evidence' => ''],
        ['criterion' => 'title', 'text' => 'Backend-udvikler', 'verdict' => 'partial', 'decided_by' => 'llm', 'reason' => 'Close.', 'evidence' => ''],
    ],
];
$saved_documents = Match_documents::from(7, '', Match_documents::written_of($saved), ['application' => true, 'resume' => true], null);
$show(Match_result::from_saved($saved, true, $saved_documents));

// Not saved: the reason instead of the boxes.
var_dump(Match_documents::from(null, "The match wasn't saved: no database.", [], [], null));

// What the free match found in the CV.
var_dump(Match_result::cv_found(['title' => ['Web Developer'], 'skill' => array_map(fn($n) => "Skill $n", range(1, 31)), 'experience_years' => 9])['more']);
echo Match_result::cv_found(['title' => [], 'skill' => ['PHP'], 'experience_years' => 0])['status'], "\n";
?>
--EXPECT--
81% good | Backend-udvikler · Nordisk Fragt | Requirements 75% (112.5/150) · Nice to have 0% (0/50) · Job title 0% (0/30) · Responsibilities 0% (0/30)
filters: All 3, Missing 1, Partial 1, Met 1
## Requirements (75% · weight 150)
- partial Go / Rust: Best of Go or Rust. [model]
  - missing Go [keyword match]
  - partial Rust [model]
- met PHP: Named in the CV. [taxonomy, exact]
## Job title (0% · weight 30)
- missing Backend-udvikler: No verdict returned. [model]
soft: Team player
apply: Read this before you apply: the post asks for more than a click | Posts like this often skip applications that don't follow it, such as Easy Apply. Write application below follows it for you. | mailto:job@nordiskfragt.dk?subject=Backend | It asks for a specific subject line, answers to questions.
laya: Laya: 42% likely to meet every requirement, fit 2.3 of 4 (fair).
box application: Job application / Write application / to job@nordiskfragt.dk
box resume: Tailored résumé / Tailor résumé / Your API key is switched off for writing the résumé.
--
81% good | Udvikler søges | Requirements 75% (112.5/150) · Nice to have 0% (0/50) · Job title 0% (0/30) · Responsibilities 0% (0/30)
filters: All 3, Missing 1, Partial 1, Met 1
## Requirements (75% · weight 150)
- partial Go / Rust: Best of Go or Rust. [model]
  - missing Go [keyword match]
  - partial Rust [model]
- met PHP: Named in the CV. [taxonomy, exact]
## Job title (0% · weight 30)
- missing Backend-udvikler: No verdict returned. [model]
soft: Team player
apply: -
laya: No Laya reading this time. Laya is down. The score above doesn't depend on it.
--
81% good | Backend-udvikler · Nordisk Fragt | 112.5/260 points · saved 2026-10-21T09:30:00Z
filters: All 3, Missing 1, Partial 1, Met 1
## Requirements
- missing 5 years: Has 3. [rule]
- met PHP: Named. [taxonomy, exact]
## Job title
- partial Backend-udvikler: Close. [model]
soft: 
apply: -
laya: 
box application: Job application / Write it again
box resume: Tailored résumé / Tailor résumé
--
array(3) {
  ["id"]=>
  NULL
  ["error"]=>
  string(36) "The match wasn't saved: no database."
  ["boxes"]=>
  array(0) {
  }
}
string(10) "and 2 more"
Found 1 skills and 0 job titles we know.
