--TEST--
a tailored résumé with only projects is kept, and its text lists them under their heading after experience
--FILE--
<?php
require_once __DIR__ . '/../Tailored_resume.php';

$entry = fn(string $title) => ['title' => $title, 'organisation' => '', 'location' => '', 'starts' => 'Januar 2026', 'ends' => 'nu', 'summary' => '', 'bullets' => ['Kører på Kubernetes'], 'note' => ''];
$resume = Tailored_resume::clean([
    'name' => 'Jonas Hansen', 'title' => 'Backend-udvikler', 'location' => '', 'phone' => '', 'email' => '', 'links' => [], 'intro' => [],
    'experience' => [], 'projects' => [$entry('Trongate.cloud')], 'education' => [], 'skills' => ['PHP'], 'languages' => [], 'education_note' => '',
    'experience_heading' => 'Erfaring', 'projects_heading' => 'Projekter', 'skills_heading' => 'Kompetencer', 'education_heading' => 'Uddannelse', 'languages_heading' => 'Sprog',
]);
echo Tailored_resume::text($resume), "\n";
?>
--EXPECT--
Jonas Hansen
Backend-udvikler

Projekter

Trongate.cloud
Januar 2026 – nu
- Kører på Kubernetes

Kompetencer
- PHP
