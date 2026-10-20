--TEST--
Job_search_rules::normal_url writes the same post's links the same way
--FILE--
<?php
require __DIR__ . '/../Job_search_rules.php';
foreach ([
    'https://www.Jobnet.dk/job/123/?utm_source=x&b=2&a=1#apply',
    'HTTPS://jobnet.dk/job/123?a=1&b=2&fbclid=abc',
    'https://www.linkedin.com/jobs/view/42/?refId=xyz&trackingId=q',
    'not a url',
] as $url) {
    echo Job_search_rules::normal_url($url), "\n";
}
?>
--EXPECT--
https://jobnet.dk/job/123?a=1&b=2
https://jobnet.dk/job/123?a=1&b=2
https://linkedin.com/jobs/view/42
not a url
