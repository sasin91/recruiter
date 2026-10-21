--TEST--
Job_scan_mcp answers initialize, tools/list and tools/call, nothing to notifications, and checks what it is sent
--FILE--
<?php
require __DIR__ . '/../Job_scan_mcp.php';
$sent = null;
$mcp = new Job_scan_mcp(function (array $posts) use (&$sent) {
    $sent = $posts;
    return ['added' => [['url' => $posts[0]['url'], 'id' => 7]], 'skipped' => [['url' => 'ftp://x', 'reason' => 'Not an http(s) link.']]];
}, 'https://r.test/job_scan/search/3', 4);
$ask = fn(array $message) => $mcp->answer(json_encode($message));
$show = fn($reply) => print(json_encode($reply, JSON_UNESCAPED_SLASHES) . "\n");
$call = fn($arguments) => $ask(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'add_job_links', 'arguments' => $arguments]]);
$tool_text = fn($reply) => print(($reply['result']['isError'] ?? false ? 'error: ' : '') . $reply['result']['content'][0]['text'] . "\n");

$init = $ask(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']]);
echo $init['result']['protocolVersion'], ' ', json_encode($init['result']['capabilities']), "\n";
echo $ask(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '1999-01-01']])['result']['protocolVersion'], "\n";
var_dump($ask(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
$show($ask(['jsonrpc' => '2.0', 'id' => 'p', 'method' => 'ping']));
$tools = $ask(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])['result']['tools'];
echo count($tools), ' ', $tools[0]['name'], ' ', $tools[0]['inputSchema']['properties']['posts']['maxItems'], "\n";

echo "-- calls\n";
$show($call(['posts' => [['url' => 'https://jobs.test/1', 'title' => 'Dev'], ['url' => 'ftp://x']]]));
echo count($sent), "\n";
$sent = null;
$tool_text($call(['posts' => 'x']));
$tool_text($call(['posts' => []]));
$tool_text($call(['posts' => [['url' => 'https://a'], ['url' => 'https://b'], ['url' => 'https://c'], ['url' => 'https://d'], ['url' => 'https://e']]]));
$tool_text($call(['posts' => ['https://a', ['url' => ''], ['url' => 'https://b', 'title' => 5, 'salary' => '1'], ['url' => str_repeat('x', 2049)]]]));
var_dump($sent);

echo "-- protocol\n";
$show($ask(['jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call', 'params' => ['name' => 'other']]));
$show($ask(['jsonrpc' => '2.0', 'id' => 6, 'method' => 'resources/list']));
$show($ask(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'ping', 'params' => [1, 2]]));
$show($ask(['jsonrpc' => '2.0', 'id' => [1], 'method' => 'ping']));
$show($ask([['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']]));
$show($mcp->answer('{"jsonrpc": "2.0", '));
$show($mcp->answer(str_repeat(' ', Job_scan_mcp::MAX_BODY + 1)));
?>
--EXPECT--
2025-06-18 {"tools":{}}
2025-11-25
NULL
{"jsonrpc":"2.0","id":"p","result":{}}
1 add_job_links 4
-- calls
{"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"1 added; they are matched in a moment on https://r.test/job_scan/search/3.\nSkipped ftp://x: Not an http(s) link."}],"structuredContent":{"added":1,"skipped":[{"url":"ftp://x","reason":"Not an http(s) link."}],"search":"https://r.test/job_scan/search/3"}}}
2
error: Send posts as a list: {"posts": [{"url": "https://..."}]}.
error: Send posts as a list: {"posts": [{"url": "https://..."}]}.
error: At most 4 posts per call; send the rest in the next one.
error: posts[0] must be an object.
posts[1].url must be the post's link.
posts[2].salary is not a field (url, title, company, location).
posts[2].title must be text.
posts[3].url is over 2048 characters.
NULL
-- protocol
{"jsonrpc":"2.0","id":5,"error":{"code":-32602,"message":"No tool other; the one tool is add_job_links."}}
{"jsonrpc":"2.0","id":6,"error":{"code":-32601,"message":"No method resources/list."}}
{"jsonrpc":"2.0","id":7,"error":{"code":-32602,"message":"params must be an object."}}
{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"id must be a string or a number."}}
{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"Send one JSON-RPC 2.0 message (a batch is not taken)."}}
{"jsonrpc":"2.0","id":null,"error":{"code":-32700,"message":"The body is not JSON: Syntax error."}}
{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"The request is over 262144 bytes. Send fewer posts at a time."}}
