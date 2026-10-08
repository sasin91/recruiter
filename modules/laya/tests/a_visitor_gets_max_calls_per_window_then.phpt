--TEST--
a visitor gets MAX calls per window, then waits for the oldest to expire
--FILE--
<?php
require __DIR__ . '/setup.inc';
$dir = sys_get_temp_dir() . '/laya-limit-test-' . bin2hex(random_bytes(4));
$limit = new Laya_limit($dir, 3, 600);
var_dump($limit->take('203.0.113.7', 1000));
var_dump($limit->take('203.0.113.7', 1100));
var_dump($limit->take('203.0.113.7', 1200));
echo 'fourth call: ';
var_dump($limit->take('203.0.113.7', 1300));
echo 'another visitor: ';
var_dump($limit->take('198.51.100.2', 1300));
echo 'after the first expired: ';
var_dump($limit->take('203.0.113.7', 1601));
array_map('unlink', glob("$dir/*"));
rmdir($dir);
?>
--EXPECT--
int(0)
int(0)
int(0)
fourth call: int(300)
another visitor: int(0)
after the first expired: int(0)
