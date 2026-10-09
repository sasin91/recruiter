--TEST--
changing a requirement makes a new version; changing responsibilities doesn't
--FILE--
<?php
require __DIR__ . '/setup.inc';
$rows = Job_post_rules::from_reading($reading);
$same = $rows;
$same[] = ['kind' => 'responsibility_area', 'raw_text' => 'Opvask', 'english' => null, 'is_required' => 0, 'alt_group' => null, 'min_years' => null, 'min_level' => null];
echo 'a responsibility added: ';
var_dump(Job_post_rules::same_requirements($rows, $same));
$changed = $rows;
$changed[3]['is_required'] = 0;
echo 'a requirement made optional: ';
var_dump(Job_post_rules::same_requirements($rows, $changed));
?>
--EXPECT--
a responsibility added: bool(true)
a requirement made optional: bool(false)
