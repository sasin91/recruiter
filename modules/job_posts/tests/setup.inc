<?php
require_once __DIR__ . '/../Job_post_rules.php';

// What Match_prompts::read_job answers for "Uddannet kok eller 5 års
// erfaring, kørekort B, engelsk er en fordel, du er glad".
$reading = [
    'job_title' => 'Kok',
    'job_title_en' => 'Chef',
    'requirements' => [
        ['value' => 'Uddannet kok', 'english' => 'Trained chef', 'kind' => 'education', 'required' => true, 'alt_group' => 'b', 'min_years' => 0, 'min_level' => ''],
        ['value' => '5 års erfaring', 'english' => '5 years of experience', 'kind' => 'experience', 'required' => false, 'alt_group' => 'b', 'min_years' => 5, 'min_level' => ''],
        ['value' => 'Kørekort B', 'english' => 'Driving licence B', 'kind' => 'certificate', 'required' => true, 'alt_group' => '', 'min_years' => 0, 'min_level' => ''],
        ['value' => 'Engelsk', 'english' => 'English', 'kind' => 'language', 'required' => false, 'alt_group' => '', 'min_years' => 0, 'min_level' => 'fluent'],
        ['value' => 'Glad', 'english' => 'Cheerful', 'kind' => 'soft_skill', 'required' => true, 'alt_group' => '', 'min_years' => 0, 'min_level' => ''],
        ['value' => 'Ukendt', 'english' => '', 'kind' => 'hobby', 'required' => true, 'alt_group' => '', 'min_years' => 0, 'min_level' => ''],
    ],
    'responsibilities' => ['Frokost til 200', ''],
];
