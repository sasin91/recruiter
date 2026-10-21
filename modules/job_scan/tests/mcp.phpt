--TEST--
Job_scan_mcp answers initialize, tools/list and tools/call, and nothing to notifications
--FILE--
<?php
require __DIR__ . '/../Job_scan_mcp.php';
$sent = null;
$mcp = new Job_scan_mcp(function (array $posts) use (&$sent) {
    $sent = $posts;
    return ['added' => [['url' => $posts[0]['url'], 'id' => 7]], 'skipped' => [['url' => 'ftp://x', 'reason' => 'Not an http(s) link.']]];
}, 'https://r.test/job_scan/search/3', 50);
$show = fn($reply) => print(json_encode($reply, JSON_UNESCAPED_SLASHES) . "\n");

$init = $mcp->answer(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']]);
echo $init['result']['protocolVersion'], ' ', json_encode($init['result']['capabilities']), "\n";
echo $mcp->answer(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '1999-01-01']])['result']['protocolVersion'], "\n";
var_dump($mcp->answer(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
$show($mcp->answer(['jsonrpc' => '2.0', 'id' => 'p', 'method' => 'ping']));
$tools = $mcp->answer(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])['result']['tools'];
echo count($tools), ' ', $tools[0]['name'], ' ', str_contains($tools[0]['description'], 'At most 50') ? 'max 50' : '?', "\n";
$show($mcp->answer(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'add_job_links', 'arguments' => ['posts' => [['url' => 'https://jobs.test/1'], ['url' => 'ftp://x']]]]]));
echo count($sent), "\n";
$show($mcp->answer(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'add_job_links', 'arguments' => ['posts' => 'x']]]));
$show($mcp->answer(['jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call', 'params' => ['name' => 'other']]));
$show($mcp->answer(['jsonrpc' => '2.0', 'id' => 6, 'method' => 'resources/list']));
$show($mcp->answer(null));
?>
--EXPECT--
2025-06-18 {"tools":{}}
2025-11-25
NULL
{"jsonrpc":"2.0","id":"p","result":{}}
1 add_job_links max 50
{"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"1 added; they are matched in a moment on https://r.test/job_scan/search/3.\nSkipped ftp://x: Not an http(s) link."}],"structuredContent":{"added":1,"skipped":[{"url":"ftp://x","reason":"Not an http(s) link."}],"search":"https://r.test/job_scan/search/3"}}}
2
{"jsonrpc":"2.0","id":4,"result":{"content":[{"type":"text","text":"Send posts as a list: {\"posts\": [{\"url\": \"https://...\"}]}."}],"isError":true}}
{"jsonrpc":"2.0","id":5,"error":{"code":-32602,"message":"No tool other; the one tool is add_job_links."}}
{"jsonrpc":"2.0","id":6,"error":{"code":-32601,"message":"No method resources/list."}}
{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"Send one JSON-RPC 2.0 message."}}
