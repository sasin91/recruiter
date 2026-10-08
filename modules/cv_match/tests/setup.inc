<?php
require_once __DIR__ . '/../Cv_matcher.php';
require_once __DIR__ . '/../Page_reader.php';
require_once __DIR__ . '/../Free_reader.php';
require_once __DIR__ . '/../Pdf_writer.php';
require_once __DIR__ . '/../Tailored_resume.php';

$profile = [
    'name' => 'Test',
    'titles' => ['Web Developer', 'Tech Lead'],
    'skills' => ['PHP', 'Laravel', 'Symfony', 'Kubernetes', 'Nuxt', 'CI/CD'],
    'languages' => ['English', 'Dansk'],
    'certifications' => [],
    'education' => ['Web integrator'],
    'experience_years' => 9,
    'responsibilities' => ['Code review'],
];
$taxonomy = Taxonomy::load();
$matcher = new Cv_matcher($taxonomy, $profile);
$verdict = fn(array $item) => $matcher->decide($item + ['id' => 'x', 'kind' => 'skill']);
$req = fn(string $value, array $more = []) => $more + ['value' => $value, 'english' => $value, 'kind' => 'skill', 'required' => true, 'alt_group' => '', 'min_years' => 0, 'min_level' => ''];

$resume = <<<TEXT
Jonas Hansen
jonas@example.com | +45 12 34 56 78

Profil
Backend-udvikler med 9 års erfaring i PHP & Laravel.

Erfaring
Tech Lead, Acme ApS (2019 - 2023)
- Ledte et team på 4 <udviklere>
- Opgraderede Symfony 6 til 7

KOMPETENCER
- PHP, Laravel, Kubernetes
TEXT;

$fields = [
    'name' => ' Mette  Sørensen ',
    'title' => 'Backend-udvikler',
    'location' => 'Aarhus',
    'phone' => '+45 22 33 44 55',
    'email' => 'mette@example.dk',
    'links' => ['github.com/mette', ''],
    'intro' => ['Backend-udvikler med 7 års erfaring i PHP.'],
    'experience' => [
        ['title' => 'Senior PHP-udvikler', 'organisation' => 'Nordlys A/S', 'location' => 'Aarhus', 'starts' => 'april 2021', 'ends' => 'nu',
         'summary' => 'Logistikplatform', 'bullets' => ['Byggede en <ordre-API>', 7], 'note' => ''],
        ['title' => '', 'organisation' => 'dropped: no title', 'location' => '', 'starts' => '', 'ends' => '', 'summary' => '', 'bullets' => [], 'note' => ''],
    ],
    'skills' => ['PHP', 'Laravel'],
    'languages' => ['Dansk (modersmål)'],
    'education_note' => '',
    'education' => [
        ['title' => 'Datamatiker', 'organisation' => 'EAAA', 'location' => 'Aarhus', 'starts' => '2015', 'ends' => '2017', 'summary' => '', 'bullets' => [], 'note' => ''],
    ],
    'experience_heading' => 'Erfaring',
    'skills_heading' => 'Kompetencer',
    'education_heading' => 'Uddannelse',
    'languages_heading' => '',
];
