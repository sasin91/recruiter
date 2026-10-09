<?php
require_once __DIR__ . '/../Application_scoring.php';
require_once __DIR__ . '/../../job_posts/Job_post_rules.php';

// A post's rows as Job_posts_model::rows() gives them: "PHP; Symfony or
// Laravel; 3 years; Docker is a plus", one responsibility.
$rows = [
    ['id' => 10, 'kind' => 'title', 'raw_text' => 'Backend-udvikler', 'english' => 'Backend developer', 'is_required' => 0, 'alt_group' => null, 'min_years' => null, 'min_level' => null],
    ['id' => 11, 'kind' => 'skill', 'raw_text' => 'PHP', 'english' => 'PHP', 'is_required' => 1, 'alt_group' => null, 'min_years' => null, 'min_level' => null],
    ['id' => 12, 'kind' => 'skill', 'raw_text' => 'Symfony', 'english' => 'Symfony', 'is_required' => 1, 'alt_group' => 1, 'min_years' => null, 'min_level' => null],
    ['id' => 13, 'kind' => 'skill', 'raw_text' => 'Laravel', 'english' => 'Laravel', 'is_required' => 1, 'alt_group' => 1, 'min_years' => null, 'min_level' => null],
    ['id' => 14, 'kind' => 'experience', 'raw_text' => '3 års erfaring', 'english' => '3 years of experience', 'is_required' => 1, 'alt_group' => null, 'min_years' => 3, 'min_level' => null],
    ['id' => 15, 'kind' => 'soft_skill', 'raw_text' => 'Struktureret', 'english' => 'Structured', 'is_required' => 1, 'alt_group' => null, 'min_years' => null, 'min_level' => null],
    ['id' => 16, 'kind' => 'skill', 'raw_text' => 'Docker', 'english' => 'Docker', 'is_required' => 0, 'alt_group' => null, 'min_years' => null, 'min_level' => null],
    ['id' => 17, 'kind' => 'responsibility_area', 'raw_text' => 'Bygge API\'er', 'english' => null, 'is_required' => 0, 'alt_group' => null, 'min_years' => null, 'min_level' => null],
];
$job = Job_post_rules::to_job($rows);
$groups = Cv_matcher::criteria($job);

function verdict(string $verdict, string $by, string $method = 'exact', string $evidence = ''): array {
    return ['verdict' => $verdict, 'by' => $by, 'method' => $method, 'evidence' => $evidence, 'reason' => "Because $verdict."];
}
