<?php
/**
 * Tests for Laya_client. Run with: php modules/laya/tests.php
 *
 * No test framework, just assertions; exits non-zero if any test fails.
 */
require_once __DIR__ . '/Laya_client.php';
require_once __DIR__ . '/Laya_limit.php';

$failed = 0;

function test(string $name, callable $body): void {
    global $failed;
    try {
        $body();
        echo "ok   $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL $name\n     {$e->getMessage()} (line {$e->getLine()})\n";
    }
}

function same(mixed $actual, mixed $expected, string $message = ''): void {
    if ($actual !== $expected) {
        throw new RuntimeException(trim("$message expected " . json_encode($expected) . ', got ' . json_encode($actual)));
    }
}

test('the request asks the English checkpoint the meets and fit questions', function () {
    $request = Laya_client::request('Job: Backend developer. The candidate meets 2 of 3 required qualifications. Missing requirements: Go.');
    same($request['model'], 'english');
    same(array_keys($request['questions']), ['meets', 'fit']);
    same($request['questions']['meets']['type'], 'noul');
    same($request['questions']['fit']['type'], 'score');
    same(count($request['questions']['fit']['criteria']), 5);
});

test('the answer reads the meets probability and the expected fit', function () {
    $answer = Laya_client::read_answer(['answers' => [
        'meets' => ['type' => 'noul', 'noul' => 0.6831, 'confidence' => 0.6831],
        'fit' => ['type' => 'score', 'score' => 3.1234, 'probabilities' => []],
    ]]);
    same($answer, ['meets' => 0.6831, 'fit' => 3.123, 'fit_label' => 'good fit']);
});

test('a fit past the scale is clamped to it', function () {
    $answer = Laya_client::read_answer(['answers' => ['meets' => ['noul' => 0], 'fit' => ['score' => 4.2]]]);
    same($answer['fit'], 4.0);
    same($answer['fit_label'], 'excellent fit');
});

test('an answer without the scores is an error', function () {
    try {
        Laya_client::read_answer(['answers' => ['meets' => ['noul' => 0.5]]]);
    } catch (RuntimeException $e) {
        return;
    }
    throw new RuntimeException('no error');
});

test('a visitor gets MAX calls per window, then waits for the oldest to expire', function () {
    $dir = sys_get_temp_dir() . '/laya-limit-test-' . bin2hex(random_bytes(4));
    $limit = new Laya_limit($dir, 3, 600);
    same($limit->take('203.0.113.7', 1000), 0);
    same($limit->take('203.0.113.7', 1100), 0);
    same($limit->take('203.0.113.7', 1200), 0);
    same($limit->take('203.0.113.7', 1300), 300, 'fourth call');
    same($limit->take('198.51.100.2', 1300), 0, 'another visitor');
    same($limit->take('203.0.113.7', 1601), 0, 'after the first expired');
    array_map('unlink', glob("$dir/*"));
    rmdir($dir);
});

test('the client is the last forwarded address, else the remote address', function () {
    same(Laya_limit::client(['HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.7', 'REMOTE_ADDR' => '10.0.0.5']), '203.0.113.7');
    same(Laya_limit::client(['REMOTE_ADDR' => '10.0.0.5']), '10.0.0.5');
});

exit($failed ? 1 : 0);
