--TEST--
a 429 for a used-up quota says so and isn't retried, since waiting won't help
--FILE--
<?php
require __DIR__ . '/setup.inc';
[$server, $url] = failing_server(429, [], ['error' => [
    'message' => 'You exceeded your current quota, please check your plan and billing details.',
    'type' => 'insufficient_quota',
    'code' => 'insufficient_quota',
]]);
$openai = new Openai();
$openai->configure(['api_key' => 'test', 'base_url' => $url]);
try {
    $openai->web_search('system', 'user', ['type' => 'object']);
} catch (Llm_exception $e) {
    echo $e->getMessage(), "\n";
    var_dump($e->retryable);
}
proc_terminate($server);
?>
--EXPECT--
OpenAI says the key has no quota left: You exceeded your current quota, please check your plan and billing details.
bool(false)
