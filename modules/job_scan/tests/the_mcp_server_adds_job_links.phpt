--TEST--
job_scan/mcp's server lists add_job_links, adds the posts it is sent, and refuses arguments outside its schema
--FILE--
<?php
require __DIR__ . '/../Add_job_links_tool.php';
require __DIR__ . '/../../ai/mcp/Mcp_server.php';
$sent = null;
$tool = new Add_job_links_tool(function (array $posts) use (&$sent) {
    $sent = $posts;
    return ['added' => [['url' => $posts[0]['url'], 'id' => 7]], 'skipped' => [['url' => 'ftp://x', 'reason' => 'Not an http(s) link.']]];
}, 'https://r.test/job_scan/search/3', 4);
$mcp = new Mcp_server('recruiter-job-scan', '1.0.0', 'Send the job posts you find to add_job_links.', [$tool]);
$ask = fn(array $message) => $mcp->answer(json_encode($message));
$show = fn($reply) => print(json_encode($reply, JSON_UNESCAPED_SLASHES) . "\n");
$call = fn($arguments) => $ask(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'add_job_links', 'arguments' => $arguments]]);
$tool_text = fn($reply) => print(($reply['result']['isError'] ?? false ? 'error: ' : '') . $reply['result']['content'][0]['text'] . "\n");

echo json_encode(Mcp_schema::unsupported($tool->schema())), "\n";
$tools = $ask(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])['result']['tools'];
echo count($tools), ' ', $tools[0]['name'], ' ', $tools[0]['inputSchema']['properties']['posts']['maxItems'], ' ', json_encode($tools[0]['annotations']), "\n";

$show($call(['posts' => [['url' => 'https://jobs.test/1', 'title' => 'Dev'], ['url' => 'ftp://x']]]));
echo count($sent), "\n";
$sent = null;
$tool_text($call(['posts' => 'x']));
$tool_text($call(['posts' => []]));
$tool_text($call(['posts' => [['url' => 'https://a'], ['url' => 'https://b'], ['url' => 'https://c'], ['url' => 'https://d'], ['url' => 'https://e']]]));
$tool_text($call(['posts' => ['https://a', ['url' => ''], ['url' => 'https://b', 'title' => 5, 'salary' => '1'], ['url' => str_repeat('x', 2049)]]]));
var_dump($sent);
?>
--EXPECT--
[]
1 add_job_links 4 {"readOnlyHint":false,"destructiveHint":false,"idempotentHint":true,"openWorldHint":false}
{"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"1 added; they are matched in a moment on https://r.test/job_scan/search/3.\nSkipped ftp://x: Not an http(s) link."}],"structuredContent":{"added":1,"skipped":[{"url":"ftp://x","reason":"Not an http(s) link."}],"search":"https://r.test/job_scan/search/3"}}}
2
error: posts must be a list.
error: posts must have at least 1 item.
error: posts has 5 items; at most 4 per call, so send the rest in the next one.
error: posts[0] must be an object.
posts[1].url must not be empty.
posts[2].salary is not a field (url, title, company, location).
posts[2].title must be a string.
posts[3].url is over 2048 characters.
NULL
