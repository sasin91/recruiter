--TEST--
the review form becomes rows: blank and removed rows dropped, letters become OR-groups, problems listed
--FILE--
<?php
require __DIR__ . '/setup.inc';
[$rows, $errors] = Job_post_rules::from_form([
    'title' => ' Kok ',
    'title_en' => 'Chef',
    'requirements' => [
        ['value' => 'Uddannet kok', 'kind' => 'education', 'required' => '1', 'group' => 'c'],
        ['value' => '5 års erfaring', 'kind' => 'experience', 'group' => 'C', 'min_years' => '5'],
        ['value' => 'Kørekort B', 'kind' => 'certificate', 'required' => '1', 'remove' => '1'],
        ['value' => '', 'kind' => 'skill'],
        ['value' => 'Engelsk', 'kind' => 'language', 'min_level' => 'fluent'],
    ],
    'responsibilities' => "Frokost\r\n\r\nAftensmad",
]);
var_dump($errors);
foreach ($rows as $row) {
    echo "{$row['kind']}: {$row['raw_text']} required={$row['is_required']} group=", var_export($row['alt_group'], true), ' years=', var_export($row['min_years'], true), "\n";
}

[, $errors] = Job_post_rules::from_form([
    'title' => '',
    'requirements' => [
        ['value' => 'X', 'kind' => 'hobby'],
        ['value' => 'Y', 'kind' => 'skill', 'min_years' => 'many'],
        ['value' => 'Z', 'kind' => 'skill', 'group' => 'AB'],
        ['value' => 'W', 'kind' => 'skill', 'min_level' => str_repeat('x', 25)],
    ],
]);
echo implode("\n", $errors), "\n";
?>
--EXPECT--
array(0) {
}
title: Kok required=0 group=NULL years=NULL
education: Uddannet kok required=1 group=1 years=NULL
experience: 5 års erfaring required=1 group=1 years=5
language: Engelsk required=0 group=NULL years=NULL
responsibility_area: Frokost required=0 group=NULL years=NULL
responsibility_area: Aftensmad required=0 group=NULL years=NULL
The post needs a title.
Requirement 1: pick what kind it is.
Requirement 2: years is a whole number up to 50.
Requirement 3: an OR-group is one letter, like A.
Requirement 4: the level is at most 24 characters.
