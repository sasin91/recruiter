--TEST--
only public addresses are fetched
--FILE--
<?php
require __DIR__ . '/setup.inc';
foreach (['93.184.215.14', '1.1.1.1', '2606:4700:4700::1111'] as $ip) {
    echo $ip, ': ';
    var_dump(Page_reader::is_public($ip));
}
foreach (['127.0.0.1', '10.1.2.3', '172.20.0.1', '192.168.1.1', '169.254.169.254', '100.100.1.1', '0.0.0.0',
    '224.0.0.1', '255.255.255.255', '::1', '::', 'fe80::1', 'fd00::1', '::ffff:127.0.0.1', '64:ff9b::a00:1',
    '2001:db8::1', '2002:7f00:1::1', 'ff02::1', 'nonsense'] as $ip) {
    echo $ip, ': ';
    var_dump(Page_reader::is_public($ip));
}
?>
--EXPECT--
93.184.215.14: bool(true)
1.1.1.1: bool(true)
2606:4700:4700::1111: bool(true)
127.0.0.1: bool(false)
10.1.2.3: bool(false)
172.20.0.1: bool(false)
192.168.1.1: bool(false)
169.254.169.254: bool(false)
100.100.1.1: bool(false)
0.0.0.0: bool(false)
224.0.0.1: bool(false)
255.255.255.255: bool(false)
::1: bool(false)
::: bool(false)
fe80::1: bool(false)
fd00::1: bool(false)
::ffff:127.0.0.1: bool(false)
64:ff9b::a00:1: bool(false)
2001:db8::1: bool(false)
2002:7f00:1::1: bool(false)
ff02::1: bool(false)
nonsense: bool(false)
