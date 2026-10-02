<?php

declare(strict_types=1);

/**
 * Temporary community rules that need no database (Plan §6.5, §19).
 *
 *     C:\xampp\php\php.exe tests\community.php
 */

require_once __DIR__ . '/../app/autoload.php';

$checks = 0;

function communityCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$ok = [
    'account_active'  => true,
    'home_active'     => true,
    'home_division'   => 1,
    'division_active' => true,
    'open_temporary'  => 0,
    'proof_type'      => 'lease',
    'has_proof'       => true,
];

communityCheck(CommunityService::applicationErrors(4, $ok) === [], 'A complete application must pass.');
$checks++;

foreach ([
    'same as home'           => [1, [], 'division'],
    'second temporary'       => [4, ['open_temporary' => 1], 'division'],
    'archived division'      => [2, ['division_active' => false], 'division'],
    'missing proof'          => [4, ['has_proof' => false], 'proof'],
    'unknown proof type'     => [4, ['proof_type' => 'selfie'], 'proof_type'],
    'unverified account'     => [4, ['account_active' => false], 'form'],
    'home not active'        => [4, ['home_active' => false], 'form'],
] as $label => [$division, $changes, $field]) {
    $errors = CommunityService::applicationErrors($division, array_replace($ok, $changes));
    communityCheck(isset($errors[$field]), 'Must refuse: ' . $label);
    $checks++;
}

// Six months from approval.
$approved = new DateTimeImmutable('2026-01-15 10:00:00');
communityCheck(CommunityService::expiryFrom($approved)->format('Y-m-d') === '2026-07-15', 'A membership must last 6 months.');
$checks++;

// 14 days of grace after expiry.
communityCheck(
    CommunityService::graceEnds(new DateTimeImmutable('2026-07-15'))->format('Y-m-d') === '2026-07-29',
    'The grace period must be 14 days.'
);
$checks++;

// Extending before expiry counts from the expiry date…
$now = new DateTimeImmutable('2026-07-01');
communityCheck(
    CommunityService::renewedExpiry(new DateTimeImmutable('2026-07-15'), $now)->format('Y-m-d') === '2027-01-15',
    'An early extension must count from the expiry date.'
);
// …and after a lapse, from today.
$late = new DateTimeImmutable('2026-07-20');
communityCheck(
    CommunityService::renewedExpiry(new DateTimeImmutable('2026-07-15'), $late)->format('Y-m-d') === '2027-01-20',
    'An extension in the grace period must count from today.'
);
$checks += 2;

// Dates the forms post.
foreach (['2026-02-28' => true, '2028-02-29' => true, '2026-02-30' => false, '2026-13-01' => false, '01/02/2026' => false, '' => false] as $value => $valid) {
    communityCheck(Validator::isDate((string) $value) === $valid, 'Date check wrong for "' . $value . '".');
    $checks++;
}

echo 'Passed: ' . $checks . " temporary community checks — refusals, 6-month term, 14-day grace, extensions, dates.\n";
