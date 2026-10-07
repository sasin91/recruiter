<?php
/**
 * Tests for Key_vault. Run with: php modules/account/tests.php
 *
 * No test framework, just assertions; exits non-zero if any test fails.
 */
require_once __DIR__ . '/Key_vault.php';

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

$secret = str_repeat('s3cret-', 6);
$key = 'sk-proj-abcdefghijklmnopqrstuvwxyz0123456789';

test('a key comes back for its owner', function () use ($secret, $key) {
    $vault = new Key_vault($secret);
    $stored = $vault->encrypt($key, 7);
    same(str_contains($stored, 'abcdefghijklmnop'), false, 'stored in the clear:');
    same($vault->decrypt($stored, 7), $key);
});

test('each encryption is different', function () use ($secret, $key) {
    $vault = new Key_vault($secret);
    same($vault->encrypt($key, 7) === $vault->encrypt($key, 7), false);
});

test("another user, another secret or a tampered row can't read it", function () use ($secret, $key) {
    $vault = new Key_vault($secret);
    $stored = $vault->encrypt($key, 7);
    same($vault->decrypt($stored, 8), null, 'other user:');
    same((new Key_vault($secret . 'x'))->decrypt($stored, 7), null, 'other secret:');
    $raw = base64_decode($stored);
    $raw[30] = chr(ord($raw[30]) ^ 1);
    same($vault->decrypt(base64_encode($raw), 7), null, 'tampered:');
    same($vault->decrypt('not base64!', 7), null, 'garbage:');
});

test('a short secret is refused', function () {
    try {
        new Key_vault('short');
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException('no exception');
});

exit($failed ? 1 : 0);
