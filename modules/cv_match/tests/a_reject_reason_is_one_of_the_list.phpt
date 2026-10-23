--TEST--
a reject reason is one of the fixed list, Other needs a note, and it reads as one line
--FILE--
<?php
require_once __DIR__ . '/../Reject_reason.php';
foreach ([['commute', ''], ['other', '  '], ['other', 'Recruiter agency'], ['nope', ''], [['commute'], ''], [null, ''], ['salary', str_repeat('x', 256)]] as [$reason, $note]) {
    echo json_encode($reason), ': ', Reject_reason::problem($reason, $note) ?: 'ok', "\n";
}
echo Reject_reason::describe('commute', ' 1h each way '), "\n";
echo Reject_reason::describe('commute', ''), "\n";
echo Reject_reason::describe('other', 'Recruiter agency'), "\n";
echo Reject_reason::describe(null, ''), "|\n";
?>
--EXPECT--
"commute": ok
"other": Say in a few words why you are not interested.
"other": ok
"nope": Pick why you are not interested.
["commute"]: Pick why you are not interested.
null: Pick why you are not interested.
"salary": Keep the note under 255 characters.
Too long commute (1h each way)
Too long commute
Recruiter agency
|
