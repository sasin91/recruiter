--TEST--
the profile's CV form starts with the CV of the latest saved match, named in a note, and is empty without one
--FILE--
<?php
require_once __DIR__ . '/../Profile_page.php';

echo json_encode(Profile_page::fill(['cv_name' => 'cv.pdf', 'cv_text' => 'Jonas Hansen, developer']), JSON_UNESCAPED_SLASHES), "\n";
echo Profile_page::fill(['cv_name' => '', 'cv_text' => 'pasted'])['note'], "\n";
echo json_encode(Profile_page::fill(null)), "\n";
echo json_encode(Profile_page::from([], null, [], [])['fill']), "\n";
?>
--EXPECT--
{"cv_name":"cv.pdf","cv_text":"Jonas Hansen, developer","note":"Filled with the CV of your latest match (cv.pdf). Pick another file to use a different one."}
Filled with the CV of your latest match. Pick another file to use a different one.
{"cv_name":"","cv_text":"","note":""}
{"cv_name":"","cv_text":"","note":""}
