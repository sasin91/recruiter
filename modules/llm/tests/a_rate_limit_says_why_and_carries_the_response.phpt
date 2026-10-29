--TEST--
a 429 from OpenAI says which limit was hit, and carries the response for the log
--FILE--
<?php
require __DIR__ . '/setup.inc';
[$server, $url] = failing_server(429, [
    'retry-after' => '20',
    'x-ratelimit-limit-tokens' => '200000',
    'x-ratelimit-remaining-tokens' => '0',
    'x-ratelimit-reset-tokens' => '19.6s',
    'x-request-id' => 'req_123',
    'set-cookie' => 'not=logged',
], ['error' => [
    'message' => 'Rate limit reached for gpt-5.4-mini on tokens per min (TPM): Limit 200000, Used 199000, Requested 4000.',
    'type' => 'tokens',
    'code' => 'rate_limit_exceeded',
]]);
$openai = new Openai();
$openai->configure(['api_key' => 'test', 'base_url' => $url]);
try {
    $openai->structured('system', 'user', ['type' => 'object']);
} catch (Llm_exception $e) {
    echo $e->getMessage(), "\n";
    var_dump($e->retryable, $e->response->status, $e->response->headers['x-ratelimit-remaining-tokens']);
    $details = $e->details();
    var_dump(str_contains($details, 'x-ratelimit-reset-tokens: 19.6s'), str_contains($details, 'x-request-id: req_123'), str_contains($details, '"code":"rate_limit_exceeded"'), str_contains($details, 'set-cookie'));
}
proc_terminate($server);
?>
--EXPECT--
OpenAI rate limited the request: Rate limit reached for gpt-5.4-mini on tokens per min (TPM): Limit 200000, Used 199000, Requested 4000. Try again in 20 seconds.
bool(true)
int(429)
string(1) "0"
bool(true)
bool(true)
bool(true)
bool(false)
