--TEST--
a pasted URL is checked before anything is fetched
--FILE--
<?php
require __DIR__ . '/setup.inc';
$refused = function (string $url, array $addresses = ['93.184.215.14']) {
    try {
        Page_reader::check_url($url, fn() => $addresses);
    } catch (RuntimeException) {
        return true;
    }
    return false;
};
foreach (['file:///etc/passwd', 'gopher://example.com/', 'ftp://example.com/', 'javascript:alert(1)', 'example.com',
    'http://user:pw@example.com/', 'http://example.com:8080/', 'https://example.com:80/', 'http://127.0.0.1/',
    'http://[::1]/', 'http://2130706433/', 'http://0x7f.1/', 'http://127.1/', 'http://169.254.169.254/latest/meta-data/',
    'http://exa mple.com/'] as $url) {
    echo $url, ': ';
    var_dump($refused($url));
}
echo 'private DNS answer: ';
var_dump($refused('https://jobs.example.com/', ['10.0.0.5']));
echo 'any private DNS answer: ';
var_dump($refused('https://jobs.example.com/', ['93.184.215.14', '127.0.0.1']));
echo 'no DNS answer: ';
var_dump($refused('https://jobs.example.com/', []));

$target = Page_reader::check_url('HTTPS://Jobs.Example.com./job/1?id=2#apply', fn() => ['2606:4700::1', '93.184.215.14']);
var_dump($target['url']);
var_dump([$target['host'], $target['port'], $target['ip']]);
?>
--EXPECT--
file:///etc/passwd: bool(true)
gopher://example.com/: bool(true)
ftp://example.com/: bool(true)
javascript:alert(1): bool(true)
example.com: bool(true)
http://user:pw@example.com/: bool(true)
http://example.com:8080/: bool(true)
https://example.com:80/: bool(true)
http://127.0.0.1/: bool(true)
http://[::1]/: bool(true)
http://2130706433/: bool(true)
http://0x7f.1/: bool(true)
http://127.1/: bool(true)
http://169.254.169.254/latest/meta-data/: bool(true)
http://exa mple.com/: bool(true)
private DNS answer: bool(true)
any private DNS answer: bool(true)
no DNS answer: bool(true)
string(35) "https://jobs.example.com/job/1?id=2"
array(3) {
  [0]=>
  string(16) "jobs.example.com"
  [1]=>
  int(443)
  [2]=>
  string(13) "93.184.215.14"
}
