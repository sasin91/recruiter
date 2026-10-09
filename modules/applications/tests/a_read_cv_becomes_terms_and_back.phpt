--TEST--
a read CV becomes term rows (duplicates dropped, years as an experience row) and back
--FILE--
<?php
require __DIR__ . '/setup.inc';
$terms = Application_scoring::profile_terms([
    'title' => ['Udvikler'],
    'skill' => ['PHP', ' php ', "Docker\n compose", ''],
    'language' => ['Dansk'],
    'certificate' => [],
    'education' => ['Datamatiker'],
    'experience_years' => 7,
]);
foreach ($terms as $t) {
    echo "{$t['kind']}: {$t['raw_text']}", $t['years'] !== null ? " ({$t['years']})" : '', "\n";
}
$profile = Application_scoring::profile_from_terms($terms);
echo json_encode($profile), "\n";
?>
--EXPECT--
title: Udvikler
skill: PHP
skill: Docker compose
language: Dansk
education: Datamatiker
experience: 7 years of work (7)
{"title":["Udvikler"],"skill":["PHP","Docker compose"],"language":["Dansk"],"certificate":[],"education":["Datamatiker"],"experience_years":7,"responsibilities":[]}
