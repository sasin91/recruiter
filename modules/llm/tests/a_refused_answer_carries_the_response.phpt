--TEST--
an Llm_exception after OpenAI answered (here a refusal) carries the response
--FILE--
<?php
require __DIR__ . '/setup.inc';
[$server, $url] = status_server(200, ['x-request-id' => 'req_789'], ['choices' => [['message' => ['refusal' => 'I can not help with that.'], 'finish_reason' => 'stop']]]);
$openai = new Openai();
$openai->configure(['api_key' => 'test', 'base_url' => $url]);
try {
    $openai->structured('system', 'user', ['type' => 'object']);
} catch (Llm_exception $e) {
    echo $e->getMessage(), "\n";
    var_dump($e->response->status, str_contains($e->details(), 'x-request-id: req_789'));
}
proc_terminate($server);
?>
--EXPECT--
OpenAI declined: I can not help with that.
int(200)
bool(true)
