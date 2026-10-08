--TEST--
the client is the last forwarded address, else the remote address
--FILE--
<?php
require __DIR__ . '/setup.inc';
var_dump(Laya_limit::client(['HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.7', 'REMOTE_ADDR' => '10.0.0.5']));
var_dump(Laya_limit::client(['REMOTE_ADDR' => '10.0.0.5']));
?>
--EXPECT--
string(11) "203.0.113.7"
string(8) "10.0.0.5"
