--TEST--
the console's argument parsing and memory sizes
--FILE--
<?php
require __DIR__ . '/setup.inc';
print_r(Messenger_console::parse(['consume', 'async', 'emails', '--limit=10', '--stop-when-empty', '--memory-limit=128M']));
echo Messenger_console::bytes('128M'), ' ', Messenger_console::bytes('1G'), ' ', Messenger_console::bytes('512K'), ' ', Messenger_console::bytes('1000'), "\n";
?>
--EXPECT--
Array
(
    [0] => consume
    [1] => Array
        (
            [0] => async
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
