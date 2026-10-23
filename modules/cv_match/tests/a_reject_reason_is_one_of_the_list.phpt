--TEST--
reject reasons are one or more of the fixed list, Other needs a note, and they read as one line
--FILE--
<?php
require_once __DIR__ . '/../Reject_reason.php';
foreach ([[['commute'], ''], [['commute', 'salary'], ''], [['other'], '  '], [['salary', 'other'], 'Agency'], [[], ''], ['commute', ''], [['nope'], ''],
        [[['commute']], ''], [['x' => 'commute'], ''], [null, ''], [['salary'], str_repeat('x', 256)]] as [$reasons, $note]) {
    echo json_encode($reasons), ': ', Reject_reason::problem($reasons, $note) ?: 'ok', "\n";
}
var_dump(Reject_reason::listed('commute,salary'), Reject_reason::listed(null));
echo Reject_reason::describe(['commute', 'salary'], ' 1h each way '), "\n";
echo Reject_reason::describe(['commute'], ''), "\n";
echo Reject_reason::describe(['other'], 'Recruiter agency'), "\n";
echo Reject_reason::describe(['company', 'other'], 'Recruiter agency'), "\n";
echo Reject_reason::describe([], ''), "|\n";
?>
--EXPECT--
["commute"]: ok
["commute","salary"]: ok
["other"]: Say in a few words why you are not interested.
["salary","other"]: ok
[]: Tick why you are not interested.
"commute": Tick why you are not interested.
["nope"]: Tick why you are not interested.
[["commute"]]: Tick why you are not interested.
{"x":"commute"}: Tick why you are not interested.
null: Tick why you are not interested.
["salary"]: Keep the note under 255 characters.
array(2) {
  [0]=>
  string(7) "commute"
  [1]=>
  string(6) "salary"
}
array(0) {
}
Too long commute, Salary (1h each way)
Too long commute
Recruiter agency
The company (Recruiter agency)
|
