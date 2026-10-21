--TEST--
Mcp_server answers initialize, ping, tools/list and tools/call, nothing to notifications, and refuses what isn't one JSON-RPC message
--FILE--
<?php
require __DIR__ . '/../Mcp_server.php';

class Echo_tool extends Mcp_tool {
    public array $calls = [];
    public function name(): string { return 'echo'; }
    public function title(): string { return 'Echo'; }
    public function description(): string { return 'Says it back.'; }
    public function schema(): array {
        return ['type' => 'object', 'required' => ['text'], 'additionalProperties' => false, 'properties' => ['text' => ['type' => 'string', 'maxLength' => 5]]];
    }
    public function handle(array $arguments): Mcp_reply {
        $this->calls[] = $arguments;
        if ($arguments['text'] === 'boom') {
            throw new RuntimeException('the database is down');
        }
        return Mcp_reply::text($arguments['text'], ['said' => $arguments['text']]);
    }
}

class Plain_tool extends Mcp_tool {
    public function name(): string { return 'plain'; }
    public function description(): string { return 'No title, no annotations, no arguments.'; }
    public function schema(): array { return ['type' => 'object']; }
    public function handle(array $arguments): Mcp_reply { return Mcp_reply::error('Nothing to do.'); }
}

$echo = new Echo_tool();
$server = new Mcp_server('test-server', '2.0.0', 'Use echo.', [$echo, new Plain_tool()]);
$ask = fn(array $message) => $server->answer(json_encode($message));
$show = fn($reply) => print(json_encode($reply, JSON_UNESCAPED_SLASHES) . "\n");
$call = fn(string $name, $arguments) => $ask(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => $arguments]]);

$show($ask(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']]));
echo $ask(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '1999-01-01']])['result']['protocolVersion'], "\n";
echo isset((new Mcp_server('quiet', '1', '', []))->answer('{"jsonrpc":"2.0","id":1,"method":"initialize"}')['result']['instructions']) ? "instructions\n" : "no instructions\n";
var_dump($ask(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
var_dump($ask(['jsonrpc' => '2.0', 'id' => 9, 'result' => []]));
$show($ask(['jsonrpc' => '2.0', 'id' => 'p', 'method' => 'ping']));
$show($ask(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']));

echo "-- calls\n";
$show($call('echo', ['text' => 'hi']));
$show($call('echo', ['text' => 'far too long', 'loud' => true]));
$show($call('echo', []));
$show($call('echo', ['text' => 'boom']));
echo count($echo->calls), "\n";
$show($call('plain', []));
$show($ask(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'plain']]));
echo json_encode(Mcp_reply::text('none', [])->to_array()), "\n";
try {
    Mcp_reply::text('a list', [1, 2]);
} catch (InvalidArgumentException $e) {
    echo $e->getMessage(), "\n";
}

echo "-- protocol\n";
$show($call('other', []));
$show($call('echo', ['a', 'b']));
$show($ask(['jsonrpc' => '2.0', 'id' => 6, 'method' => 'resources/list']));
$show($ask(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'ping', 'params' => [1, 2]]));
$show($ask(['jsonrpc' => '2.0', 'id' => [1], 'method' => 'ping']));
$show($ask(['jsonrpc' => '1.0', 'id' => 1, 'method' => 'ping']));
$show($ask([['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']]));
$show($server->answer('{"jsonrpc": "2.0", '));
$show($server->answer('"text"'));
$show($server->answer(str_repeat(' ', Mcp_server::MAX_BODY + 1)));
?>
--EXPECTF--
{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-06-18","capabilities":{"tools":{}},"serverInfo":{"name":"test-server","version":"2.0.0"},"instructions":"Use echo."}}
2025-11-25
no instructions
NULL
NULL
{"jsonrpc":"2.0","id":"p","result":{}}
{"jsonrpc":"2.0","id":2,"result":{"tools":[{"name":"echo","title":"Echo","description":"Says it back.","inputSchema":{"type":"object","required":["text"],"additionalProperties":false,"properties":{"text":{"type":"string","maxLength":5}}}},{"name":"plain","description":"No title, no annotations, no arguments.","inputSchema":{"type":"object"}}]}}
-- calls
{"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"hi"}],"structuredContent":{"said":"hi"}}}
{"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"loud is not a field (text).\ntext is over 5 characters."}],"isError":true}}
{"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"text is missing."}],"isError":true}}
%Amcp: the test-server tool echo failed: the database is down
{"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"echo failed on the server, and the error is logged. Try again later."}],"isError":true}}
2
{"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"Nothing to do."}],"isError":true}}
{"jsonrpc":"2.0","id":4,"result":{"content":[{"type":"text","text":"Nothing to do."}],"isError":true}}
{"content":[{"type":"text","text":"none"}],"structuredContent":{}}
Structured content must have keys (a JSON object), not be a list.
-- protocol
{"jsonrpc":"2.0","id":3,"error":{"code":-32602,"message":"No tool other; the tools are: echo, plain."}}
{"jsonrpc":"2.0","id":3,"error":{"code":-32602,"message":"arguments must be an object."}}
{"jsonrpc":"2.0","id":6,"error":{"code":-32601,"message":"No method resources/list."}}
{"jsonrpc":"2.0","id":7,"error":{"code":-32602,"message":"params must be an object."}}
{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"id must be a string or a number."}}
{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"Send one JSON-RPC 2.0 message (a batch is not taken)."}}
{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"Send one JSON-RPC 2.0 message (a batch is not taken)."}}
{"jsonrpc":"2.0","id":null,"error":{"code":-32700,"message":"The body is not JSON: Syntax error."}}
{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"Send one JSON-RPC 2.0 message (a batch is not taken)."}}
{"jsonrpc":"2.0","id":null,"error":{"code":-32600,"message":"The request is over 262144 bytes."}}
