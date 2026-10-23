--TEST--
reject reasons are one or more of the fixed list, each with its own note (Other needs one), and they read as one line
--FILE--
<?php
require_once __DIR__ . '/../Reject_reason.php';
foreach ([
    [['commute'], []],
    [['commute', 'salary'], ['commute' => '1h each way']],
    [['other'], ['other' => '  ']],
    [['other'], ['commute' => 'Agency']],
    [['salary', 'other'], ['other' => 'Agency']],
    [[], []], ['commute', []], [['nope'], []], [[['commute']], []], [['x' => 'commute'], []], [null, null],
    [['salary'], ['salary' => str_repeat('x', 256)]],
    [['salary'], 'not a list'],
] as [$reasons, $notes]) {
    echo json_encode($reasons), ' ', json_encode(is_array($notes) ? array_map(fn($n) => is_string($n) && strlen($n) > 20 ? strlen($n) . ' chars' : $n, $notes) : $notes), ': ', Reject_reason::problem($reasons, $notes) ?: 'ok', "\n";
}
// A note for a reason not ticked is left out; a ticked one without a note gets ''.
var_dump(Reject_reason::rows(['commute', 'salary', 'commute'], ['commute' => ' 1h each way ', 'company' => 'x', 'salary' => ['no']]));
echo Reject_reason::describe([['reason' => 'commute', 'note' => '1h each way'], ['reason' => 'salary', 'note' => ''], ['reason' => 'other', 'note' => 'Recruiter agency']]), "\n";
echo Reject_reason::describe([['reason' => 'other', 'note' => '']]), "\n";
echo Reject_reason::describe([]), "|\n";
?>
--EXPECT--
["commute"] []: ok
["commute","salary"] {"commute":"1h each way"}: ok
["other"] {"other":"  "}: Say in a few words what the other reason is.
["other"] {"commute":"Agency"}: Say in a few words what the other reason is.
["salary","other"] {"other":"Agency"}: ok
[] []: Tick why you are not interested.
"commute" []: Tick why you are not interested.
["nope"] []: Tick why you are not interested.
[["commute"]] []: Tick why you are not interested.
{"x":"commute"} []: Tick why you are not interested.
null null: Tick why you are not interested.
["salary"] {"salary":"256 chars"}: Keep each note under 255 characters.
["salary"] "not a list": ok
array(2) {
  [0]=>
  array(2) {
    ["reason"]=>
    string(7) "commute"
    ["note"]=>
    string(11) "1h each way"
  }
  [1]=>
  array(2) {
    ["reason"]=>
    string(6) "salary"
    ["note"]=>
    string(0) ""
  }
}
Too long commute (1h each way), Salary, Recruiter agency
Other
|
