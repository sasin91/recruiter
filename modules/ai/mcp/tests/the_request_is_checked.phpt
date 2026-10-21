--TEST--
Mcp_request refuses what isn't a JSON POST from an allowed origin in a spoken protocol version
--FILE--
<?php
require __DIR__ . '/../Mcp_request.php';
$origins = ['https://recruiter.test', 'https://chatgpt.com'];
$versions = ['2025-11-25', '2025-06-18'];
$check = function (string $method, string $type = 'application/json', string $origin = '', string $version = '') use ($origins, $versions) {
    $refusal = (new Mcp_request($method, $type, $origin, $version, '{}'))->refusal($origins, $versions);
    echo $refusal === null ? 'ok' : "$refusal[0] $refusal[1]", "\n";
};
$check('POST');
$check('POST', 'application/json; charset=utf-8', 'https://chatgpt.com', '2025-06-18');
$check('POST', 'Application/JSON', 'https://Recruiter.test');
$check('GET');
$check('POST', 'text/plain');
$check('POST', '');
$check('POST', 'application/json', 'https://evil.test');
$check('POST', 'application/json', 'https://chatgpt.com.evil.test');
$check('POST', 'application/json', 'http://localhost:8080');
$check('POST', 'application/json', '', '2024-01-01');
echo Mcp_request::origin_of('https://Recruiter.test/job_scan/'), ' ', Mcp_request::origin_of('http://localhost:8080/x'), ' [', Mcp_request::origin_of('null'), "]\n";
?>
--EXPECT--
ok
ok
ok
405 Send JSON-RPC messages with POST.
415 Send JSON (Content-Type: application/json).
415 Send JSON (Content-Type: application/json).
403 Requests from https://evil.test are not taken.
403 Requests from https://chatgpt.com.evil.test are not taken.
403 Requests from http://localhost:8080 are not taken.
400 MCP protocol version 2024-01-01 is not spoken here; use one of 2025-11-25, 2025-06-18.
https://recruiter.test http://localhost:8080 []
