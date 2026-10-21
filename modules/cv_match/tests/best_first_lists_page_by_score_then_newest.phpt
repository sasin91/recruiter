--TEST--
best-first lists page by score, then newest: the cursor names the last row shown
--FILE--
<?php
require_once __DIR__ . '/../Score_cursor.php';
var_dump(Score_cursor::after('0.814', 1792620000, '7'));
// A found post not matched yet sorts below every score.
var_dump(Score_cursor::after(null, '1792620000', 12));
var_dump(Score_cursor::place('0.8140_1792620000_7'));
var_dump(Score_cursor::place(Score_cursor::after(null, 5, 6)));
// Anything else is no cursor: the list starts from the top.
foreach (['', '1792620000_7', '2.0000_1_1', '0.81_1_1', "0.8140_1_1\n", '0.8140_1_1 OR 1'] as $bad) {
    var_dump(Score_cursor::place($bad));
}
?>
--EXPECT--
string(19) "0.8140_1792620000_7"
string(21) "-1.0000_1792620000_12"
array(3) {
  ["score"]=>
  string(6) "0.8140"
  ["at"]=>
  int(1792620000)
  ["id"]=>
  int(7)
}
array(3) {
  ["score"]=>
  string(7) "-1.0000"
  ["at"]=>
  int(5)
  ["id"]=>
  int(6)
}
NULL
NULL
NULL
NULL
NULL
NULL
