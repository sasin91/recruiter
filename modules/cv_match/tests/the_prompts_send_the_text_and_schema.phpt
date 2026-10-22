--TEST--
the shared prompts send the post, CV and requirements with their schemas
--FILE--
<?php
require_once __DIR__ . '/../Match_prompts.php';

// Stands in for the llm module: answers with what it was asked.
class Llm {
    public function structured(string $system, string $user, array $schema, string $effort = 'medium'): array {
        return ['system' => $system, 'user' => $user, 'required' => $schema['required'], 'effort' => $effort];
    }
}
$llm = new Llm();

$job = Match_prompts::read_job($llm, 'Kok søges');
echo $job['user'], ' | ', implode(',', $job['required']), ' | ', $job['effort'], "\n";
var_dump(str_contains($job['system'], 'alt_group'));

$cv = Match_prompts::read_cv($llm, 'Mette, kok');
echo $cv['user'], ' | ', $cv['effort'], "\n";

$judge = Match_prompts::judge($llm, 'Kok', [['id' => 'r0', 'kind' => 'certificate', 'text' => 'Kørekort B']], 'Har kørekort');
echo $judge['user'], "\n", implode(',', $judge['required']), ' | ', $judge['effort'], "\n";

// Where the candidate lives goes with a location requirement.
$judge = Match_prompts::judge($llm, 'Kok', [['id' => 'l0', 'kind' => 'location', 'text' => 'Located close to Copenhagen']], 'Mette, kok', 'Slagelse');
echo strtok($judge['user'], "\n"), ' / ', strtok("\n"), "\n";
?>
--EXPECT--
Job post:

Kok søges | text_en,job_title,job_title_en,company,requirements,responsibilities | low
bool(true)
CV:

Mette, kok | low
Job title: Kok
The candidate lives in: not stated

Requirements:
- [r0] (certificate) Kørekort B

CV:

Har kørekort
verdicts | medium
Job title: Kok / The candidate lives in: Slagelse
