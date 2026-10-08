<?php
/**
 * Tests for Company_rules. Run with: php modules/company/tests.php
 *
 * No test framework, just assertions; exits non-zero if any test fails.
 */
require_once __DIR__ . '/Company_rules.php';

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

test('a CVR number passes the modulus 11 check', function () {
    same(Company_rules::cvr('12345674'), '12345674');
    same(Company_rules::cvr(' DK 1234 5674 '), '12345674', 'typed with DK and spaces:');
    same(Company_rules::cvr('12345678'), null, 'wrong check digit:');
    same(Company_rules::cvr('1234567'), null, 'seven digits:');
    same(Company_rules::cvr('02345675'), null, 'leading zero:');
    same(Company_rules::cvr('1234567a'), null, 'a letter:');
});

$owner = ['id' => 1, 'role' => 'owner', 'active' => 1];
$other_owner = ['id' => 2, 'role' => 'owner', 'active' => 1];
$recruiter = ['id' => 3, 'role' => 'recruiter', 'active' => 1];

test('only owners change members, and never themselves', function () use ($owner, $recruiter) {
    same(Company_rules::refuse_change($recruiter, $owner, 'deactivate', 1), 'Only an owner can change members.');
    same(Company_rules::refuse_change($owner, $owner, 'make_recruiter', 2), "You can't change your own access. Ask another owner.");
    same(Company_rules::refuse_change($owner, $recruiter, 'make_owner', 1), null);
    same(Company_rules::refuse_change($owner, $recruiter, 'deactivate', 1), null);
});

test('a company keeps at least one active owner', function () use ($owner, $other_owner) {
    same(Company_rules::refuse_change($owner, $other_owner, 'deactivate', 1), 'A company needs at least one owner.');
    same(Company_rules::refuse_change($owner, $other_owner, 'make_recruiter', 1), 'A company needs at least one owner.');
    same(Company_rules::refuse_change($owner, $other_owner, 'deactivate', 2), null, 'with two owners:');
    $inactive_owner = ['id' => 2, 'role' => 'owner', 'active' => 0];
    same(Company_rules::refuse_change($owner, $inactive_owner, 'make_recruiter', 1), null, 'an inactive owner:');
});

test('unknown changes are refused', function () use ($owner, $recruiter) {
    same(Company_rules::refuse_change($owner, $recruiter, 'delete_everything', 1), 'Unknown change.');
});

test('invite tokens are 32 hex characters and checked before lookup', function () {
    $token = Company_rules::invite_token();
    same(Company_rules::is_invite_token($token), true);
    same($token === Company_rules::invite_token(), false, 'two tokens alike:');
    same(Company_rules::is_invite_token("' OR 1=1 --"), false);
    same(Company_rules::is_invite_token(strtoupper($token)), false);
});

exit($failed ? 1 : 0);
