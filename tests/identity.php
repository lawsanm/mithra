<?php

declare(strict_types=1);

/**
 * Identity checks that need no database: the rules registration applies to
 * what someone types, and the password handling behind sign-in.
 *
 *     C:\xampp\php\php.exe tests\identity.php
 *
 * The parts that need MySQL — uniqueness, the two-row insert, a moderator's
 * approval — are exercised by signing in and registering against a running
 * local database, not from here.
 */

require_once __DIR__ . '/../app/autoload.php';

$checks = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expect(string $label, mixed $actual, mixed $expected): void
{
    if ($actual !== $expected) {
        throw new RuntimeException(sprintf(
            '%s: expected %s, got %s',
            $label,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

// ── Mobile numbers ──────────────────────────────────────────────────────────
// Every way a Sri Lankan mobile is written reaches the same stored value, so
// one number cannot be registered twice and sign-in finds it however it is
// typed.

$stored = '+94 77 123 4567';

foreach (['0771234567', '077 123 4567', '077-123-4567', '+94 77 123 4567', '+94771234567', '94771234567', '0094771234567', '771234567'] as $written) {
    expect('normalisePhone(' . $written . ')', RegistrationService::normalisePhone($written), $stored);
    $checks++;
}

foreach ([
    '',
    '077123456',        // eight national digits
    '07712345678',      // ten
    '0112345678',       // a landline, not a mobile
    '0871234567',       // no such mobile prefix
    '+44 7700 900123',  // not a Sri Lankan number
    'not a number',
] as $rejected) {
    expect('normalisePhone rejects ' . var_export($rejected, true), RegistrationService::normalisePhone($rejected), null);
    $checks++;
}

expect('phoneDigits of a stored number', RegistrationService::phoneDigits($stored), '771234567');
expect('phoneDigits of a typed number', RegistrationService::phoneDigits('077 123 4567'), '771234567');
expect('phoneDigits of a short number', RegistrationService::phoneDigits('1234'), '1234');
$checks += 3;

// ── NIC numbers ─────────────────────────────────────────────────────────────

expect('old-format NIC', RegistrationService::normaliseNic('199012345v'), '199012345V');
expect('old-format NIC with X', RegistrationService::normaliseNic('912345678x'), '912345678X');
expect('new-format NIC', RegistrationService::normaliseNic('199012345671'), '199012345671');
expect('NIC with spaces', RegistrationService::normaliseNic(' 1990 12345 V '), '199012345V');
$checks += 4;

foreach (['', '19901234V', '1990123456V', '19901234567', '1990123456712', '199012345A', 'abcdefghiV'] as $rejected) {
    expect('normaliseNic rejects ' . var_export($rejected, true), RegistrationService::normaliseNic($rejected), null);
    $checks++;
}

// ── Passwords ───────────────────────────────────────────────────────────────
// The hashes registration writes are the hashes sign-in verifies, and the
// seeded accounts still open with the password the seed file documents.

$hash = password_hash('a good long phrase', PASSWORD_DEFAULT);
$checks++;
check(password_verify('a good long phrase', $hash), 'A registered password must verify.');
$checks++;
check(!password_verify('a good long phras', $hash), 'A wrong password must not verify.');
$checks++;
check(!password_verify('A good long phrase', $hash), 'Passwords are case-sensitive.');

$seeded = '$2y$10$JvMwBR8k2hpL6ZAV3XXtSe0zqu87RVh0YmNh/tFAKgiJ.fTX9QL2a';
$checks++;
check(password_verify('password', $seeded), 'The seeded demo accounts must still open with "password".');

$checks++;
check(
    RegistrationService::MAX_PASSWORD_BYTES === 72,
    'bcrypt reads 72 bytes; accepting more would silently ignore the rest.'
);
$checks++;
check(RegistrationService::MIN_PASSWORD >= 8, 'A minimum shorter than 8 characters is not a password rule.');

// ── Refusals ────────────────────────────────────────────────────────────────
// A wrong identifier and a wrong password must be refused the same way, so
// neither the message nor the timing says whether an account exists.

$absent = new ReflectionClassConstant(AuthService::class, 'ABSENT_ACCOUNT_HASH');
$checks++;
check(
    is_string($absent->getValue()) && str_starts_with((string) $absent->getValue(), '$2y$10$'),
    'The decoy hash must be a real bcrypt hash at the cost the accounts use.'
);
$checks++;
check(!password_verify('password', (string) $absent->getValue()), 'The decoy hash must match no known password.');

// ── Password policy (shared by sign-up, reset and change) ───────────────────

expect('policy accepts a long phrase', PasswordPolicy::errors('a good long phrase', 'a good long phrase'), []);
check(isset(PasswordPolicy::errors('short', 'short')['password']), 'Seven characters or fewer must be refused.');
check(isset(PasswordPolicy::errors(str_repeat('a', 73), str_repeat('a', 73))['password']), 'Over 72 bytes must be refused.');
check(isset(PasswordPolicy::errors('a good long phrase', 'a good long phrasE')['password_confirmation']), 'Mismatch must be refused.');
check(isset(PasswordPolicy::errors('', '')['password']), 'Empty must be refused.');
check(RegistrationService::MIN_PASSWORD === PasswordPolicy::MIN_LENGTH, 'Sign-up must use the shared rule.');
$checks += 6;

// ── Reset secrets ───────────────────────────────────────────────────────────

$codes = [];
for ($i = 0; $i < 200; $i++) {
    $code = PasswordResetService::newCode();
    check(preg_match('/^[A-HJKMNP-Z2-9]{5}-[A-HJKMNP-Z2-9]{5}$/', $code) === 1, 'Code shape: ' . $code);
    $codes[$code] = true;
}
check(count($codes) === 200, 'Codes must not repeat.');
expect('code typed loosely', PasswordResetService::normaliseCode(' abcde fghjk '), 'ABCDEFGHJK');
expect('code with dash', PasswordResetService::normaliseCode('ABCDE-FGHJK'), 'ABCDEFGHJK');
check(PasswordResetService::looksLikeToken(bin2hex(random_bytes(32))), 'A real token must look like one.');
check(!PasswordResetService::looksLikeToken('../../etc/passwd'), 'Junk is not a token.');
check(!PasswordResetService::looksLikeToken(strtoupper(bin2hex(random_bytes(32)))), 'Tokens are lower-case hex.');
$checks += 206;

// ── Throttle keys ───────────────────────────────────────────────────────────
// Every spelling of one account shares a counter; scopes never share one.

$phoneKey = LoginThrottle::identifierHash('login', '077 123 4567');
foreach (['+94 77 123 4567', '0771234567', '94771234567'] as $spelling) {
    expect('throttle key for ' . $spelling, LoginThrottle::identifierHash('login', $spelling), $phoneKey);
    $checks++;
}
expect('email case', LoginThrottle::identifierHash('login', ' Lawsan@Email.com'), LoginThrottle::identifierHash('login', 'lawsan@email.com'));
check(LoginThrottle::identifierHash('reset-code', '0771234567') !== $phoneKey, 'Scopes must be separate.');
$checks += 2;

// ── Session rules ───────────────────────────────────────────────────────────

$session = new SessionMiddleware();
$active  = ['status' => 'active', 'password_changed_at' => '2026-09-01 10:00:00', 'role_code' => 'member'];
$now     = 1_800_000_000;

expect('healthy session', $session->handle($active, 'member', '2026-09-01 10:00:00', $now - 60, $now), null);
expect('first request after sign-in', $session->handle($active, 'member', '2026-09-01 10:00:00', null, $now), null);
check($session->handle(null, 'member', null, $now, $now) !== null, 'A deleted account must end the session.');
check($session->handle(['status' => 'suspended'] + $active, 'member', '2026-09-01 10:00:00', $now, $now) !== null, 'Suspension must end the session.');
check($session->handle(['status' => 'closed_standard'] + $active, 'member', '2026-09-01 10:00:00', $now, $now) !== null, 'Closure must end the session.');
check($session->handle($active, 'moderator', '2026-09-01 10:00:00', $now, $now) !== null, 'A changed role must end the session.');
check($session->handle($active, 'member', null, $now, $now) !== null, 'A password changed elsewhere must end the session.');
check($session->handle($active, 'member', '2026-08-01 10:00:00', $now, $now) !== null, 'An older stamp must end the session.');
check($session->handle($active, 'member', '2026-09-01 10:00:00', $now - SessionMiddleware::IDLE_SECONDS - 1, $now) !== null, 'Idle sessions must end.');
expect('never-changed password', $session->handle(['password_changed_at' => null] + $active, 'member', null, $now, $now), null);
$checks += 10;

// ── Public pages ────────────────────────────────────────────────────────────

foreach (['/forgot-password', '/reset-password', '/reset-password/code'] as $path) {
    check(AuthMiddleware::isPublic($path), $path . ' must be reachable while signed out.');
    $checks++;
}
check(!AuthMiddleware::isPublic('/account/password'), 'Changing a password needs a session.');
$checks++;

echo 'Passed: ' . $checks . " identity checks — normalisation, passwords, reset secrets, throttling, sessions and refusals.\n";
