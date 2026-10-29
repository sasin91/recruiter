--TEST--
a rejected key keeps only the start of the key in the message and the log
--FILE--
<?php
require __DIR__ . '/setup.inc';
[$server, $url] = status_server(401, [], ['error' => [
    'message' => 'Incorrect API key provided: sk-proj-abcdefghijklmnop. You can find your API key at https://platform.openai.com/account/api-keys.',
    'code' => 'invalid_api_key',
]]);
$openai = new Openai();
$openai->configure(['api_key' => 'sk-proj-abcdefghijklmnop', 'base_url' => $url]);
try {
    $openai->structured('system', 'user', ['type' => 'object']);
} catch (Llm_exception $e) {
    echo $e->getMessage(), "\n";
    var_dump(str_contains($e->details(), 'abcdefghijklmnop'));
}
proc_terminate($server);
?>
--EXPECT--
OpenAI rejected the API key: Incorrect API key provided: sk-… You can find your API key at https://platform.openai.com/account/api-keys.
bool(false)
