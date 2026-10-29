--TEST--
a 429 from Claude says which limit was hit, and carries the response for the log
--SKIPIF--
<?php if (!is_file(__DIR__ . '/../../../packages/autoload.php')) echo 'skip composer install puts the Anthropic SDK in packages/'; ?>
--FILE--
<?php
require __DIR__ . '/setup.inc';
require __DIR__ . '/../../../packages/autoload.php';
require_once __DIR__ . '/../anthropic/Anthropic.php';
$sdk_error = \Anthropic\Core\Exceptions\APIStatusException::from(
    new \GuzzleHttp\Psr7\Request('POST', 'https://api.anthropic.com/v1/messages'),
    new \GuzzleHttp\Psr7\Response(429, ['retry-after' => '30', 'anthropic-ratelimit-output-tokens-remaining' => '0', 'request-id' => 'req_456'],
        json_encode(['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'This request would exceed the rate limit of 8,000 output tokens per minute.']])),
);
$failed = new ReflectionMethod(Anthropic::class, 'failed');
$e = $failed->invoke(null, $sdk_error);
echo $e->getMessage(), "\n";
var_dump($e->retryable, $e->response->status, str_contains($e->details(), 'anthropic-ratelimit-output-tokens-remaining: 0'), str_contains($e->details(), 'request-id: req_456'));
?>
--EXPECT--
Claude rate limited the request: This request would exceed the rate limit of 8,000 output tokens per minute.
bool(true)
int(429)
bool(true)
bool(true)
