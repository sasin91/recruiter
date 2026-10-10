--TEST--
the console's argument parsing and memory sizes
--FILE--
<?php
require __DIR__ . '/setup.inc';
print_r(Queue_console::parse(['work', 'default', 'emails', '--limit=10', '--stop-when-empty', '--memory-limit=128M']));
echo Queue_console::bytes('128M'), ' ', Queue_console::bytes('1G'), ' ', Queue_console::bytes('512K'), ' ', Queue_console::bytes('1000'), "\n";
?>
--EXPECT--
Array
(
    [0] => work
    [1] => Array
        (
            [0] => default
            [1] => emails
        )

    [2] => Array
        (
            [limit] => 10
            [stop-when-empty] => 1
            [memory-limit] => 128M
        )

)
134217728 1073741824 524288 1000
