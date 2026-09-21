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

echo 'Passed: ' . $checks . " identity checks — phone and NIC normalisation, password handling and refusals.\n";
