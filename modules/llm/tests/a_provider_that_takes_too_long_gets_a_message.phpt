--TEST--
a provider that takes too long gets a message, not a fatal error
--FILE--
<?php
require __DIR__ . '/setup.inc';
[$server, $url] = slow_server(5);
$openai = new Openai();
$openai->configure(['api_key' => 'test', 'base_url' => $url, 'timeout' => 1]);
try {
    $openai->structured('system', 'user', ['type' => 'object']);
} catch (Llm_exception $e) {
    echo $e->getMessage(), "\n";
    var_dump($e->retryable);
}
proc_terminate($server);
?>
--EXPECT--
OpenAI didn't answer within 1 seconds. Try again; if it keeps happening, try with less text.
bool(true)
