--TEST--
the about text is cut to the TEXT column's bytes, not characters, and never inside a letter
--FILE--
<?php
require_once __DIR__ . '/../Profile_fields.php';

$about = Profile_fields::header_from_form(['about' => implode("\n\n", array_fill(0, 40, str_repeat('ø', 1999)))])['about'];
var_dump(strlen($about) <= 65535, mb_check_encoding($about, 'UTF-8'), strlen($about) > 65000);
echo Profile_fields::header_from_form(['about' => "Kort.\n\n\nTo afsnit."])['about'], "\n";
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
Kort.

To afsnit.
