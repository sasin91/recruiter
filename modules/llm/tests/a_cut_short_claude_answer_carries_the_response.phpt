--TEST--
an Llm_exception after Claude answered (here cut short at max_tokens) carries the response
--SKIPIF--
<?php if (!is_file(__DIR__ . '/../../../packages/autoload.php')) echo 'skip composer install puts the Anthropic SDK in packages/'; ?>
--FILE--
<?php
require __DIR__ . '/setup.inc';
require_once __DIR__ . '/../anthropic/Anthropic.php';
[$server, $url] = status_server(200, ['request-id' => 'req_claude'], [
    'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
    'content' => [['type' => 'text', 'text' => '{"half":']],
    'stop_reason' => 'max_tokens', 'stop_sequence' => null,
    'usage' => ['input_tokens' => 1, 'output_tokens' => 16000],
]);
$claude = new Anthropic();
$claude->configure(['api_key' => 'test', 'base_url' => $url]);
try {
    $claude->structured('system', 'user', ['type' => 'object']);
} catch (Llm_exception $e) {
    echo $e->getMessage(), "\n";
    var_dump($e->response->status, str_contains($e->details(), 'request-id: req_claude'), str_contains($e->details(), '"stop_reason":"max_tokens"'));
}
proc_terminate($server);
?>
--EXPECT--
Claude ran out of tokens before finishing.
int(200)
bool(true)
bool(true)
