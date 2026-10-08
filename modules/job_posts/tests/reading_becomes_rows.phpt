--TEST--
a reading of the post becomes rows: title, requirements with numbered OR-groups sharing required, responsibilities
--FILE--
<?php
require __DIR__ . '/setup.inc';
foreach (Job_post_rules::from_reading($reading) as $row) {
    echo implode(' | ', array_map(fn($v) => var_export($v, true), $row)), "\n";
}
?>
--EXPECT--
'title' | 'Kok' | 'Chef' | 0 | NULL | NULL | NULL
'education' | 'Uddannet kok' | 'Trained chef' | 1 | 1 | NULL | NULL
'experience' | '5 års erfaring' | '5 years of experience' | 1 | 1 | 5 | NULL
'certificate' | 'Kørekort B' | 'Driving licence B' | 1 | NULL | NULL | NULL
'language' | 'Engelsk' | 'English' | 0 | NULL | NULL | 'fluent'
'soft_skill' | 'Glad' | 'Cheerful' | 1 | NULL | NULL | NULL
'responsibility_area' | 'Frokost til 200' | NULL | 0 | NULL | NULL | NULL
