<?php

declare(strict_types=1);

/**
 * Booking rules that need no database (Plan 3.1, §7.6, §8.4).
 *
 *     C:\xampp\php\php.exe tests\bookings.php
 *
 * Grows with each booking step: quotes and the request/accept/cancel rules
 * here; handover, return, late fees and claims as those modules land.
 */

require_once __DIR__ . '/common.php';

$checks = 0;

// ── Quote ───────────────────────────────────────────────────────────────────

// A one-day booking: start and end on the same day count as one day.
$one = BookingService::quote('2026-03-01', '2026-03-01', 50, null);
check($one['days'] === 1 && $one['charge'] === 50 && $one['buffer'] === 50 && $one['total'] === 100, 'One day at 50/day plus a 50 buffer.');
$checks++;

// Daily against monthly: 10 days of 50 is 500; one month of 400 is cheaper.
$ten = BookingService::quote('2026-03-01', '2026-03-10', 50, 400);
check($ten['days'] === 10 && $ten['daily_total'] === 500 && $ten['monthly_total'] === 400, '10 days quoted both ways.');
check($ten['cheaper'] === 'monthly' && $ten['basis'] === 'monthly' && $ten['charge'] === 400, 'No basis chosen: the cheaper one is used.');
check(BookingService::quote('2026-03-01', '2026-03-10', 50, 400, 'daily')['charge'] === 500, 'A chosen basis is honoured.');
check(BookingService::quote('2026-03-01', '2026-03-10', 50, null, 'monthly')['basis'] === 'daily', 'An unoffered basis falls back.');
$checks += 4;

// Monthly is charged per started 30 days.
check(BookingService::quote('2026-03-01', '2026-03-30', null, 600)['charge'] === 600, '30 days is one month.');
check(BookingService::quote('2026-03-01', '2026-03-31', null, 600)['charge'] === 1200, '31 days is two months.');
$checks += 2;

// The 27-day nudge toward monthly (§8.4).
check(!BookingService::quote('2026-03-01', '2026-03-27', 10, 200, 'daily')['nudge'], '27 days is not nudged.');
check(BookingService::quote('2026-03-01', '2026-03-28', 10, 400, 'daily')['nudge'], '28 days on the daily rate is nudged.');
check(!BookingService::quote('2026-03-01', '2026-03-28', 10, null, 'daily')['nudge'], 'No monthly rate, no nudge.');
$checks += 3;

// The late buffer is one daily rate; a monthly-only item uses monthly ÷ 30, rounded up.
check(BookingService::lateBuffer(40, 900) === 40, 'Buffer is the daily rate.');
check(BookingService::lateBuffer(null, 900) === 30, 'Monthly-only buffer: 900 / 30.');
check(BookingService::lateBuffer(null, 901) === 31, 'Monthly-only buffer rounds up.');
$checks += 3;

// Across a month boundary and a leap day.
check(BookingService::quote('2028-02-28', '2028-03-01', 10, null)['days'] === 3, 'Leap year: 28 Feb – 1 Mar is 3 days.');
$checks++;

// ── Dates ───────────────────────────────────────────────────────────────────

check(BookingService::datesErrors('2026-03-01', '2026-03-05', '2026-03-01') === [], 'Starting today is fine.');
check(isset(BookingService::datesErrors('2026-02-28', '2026-03-05', '2026-03-01')['start_date']), 'Starting yesterday is refused.');
check(isset(BookingService::datesErrors('2026-03-05', '2026-03-04', '2026-03-01')['end_date']), 'Ending before starting is refused.');
check(isset(BookingService::datesErrors('2026-03-01', '2026-12-31', '2026-03-01')['end_date']), 'Over 180 days is refused.');
$checks += 4;

// ── Who may do what, from which status ─────────────────────────────────────

check(BookingService::mayAct('accept', 'requested', true), 'The lender accepts a request.');
check(!BookingService::mayAct('accept', 'requested', false), 'The borrower cannot accept.');
check(!BookingService::mayAct('accept', 'awaiting_handover', true), 'An accepted booking cannot be accepted again.');
check(BookingService::mayAct('decline', 'requested', true), 'The lender declines a request.');
check(BookingService::mayAct('cancel', 'requested', false), 'The borrower withdraws a request.');
check(!BookingService::mayAct('cancel', 'requested', true), 'The lender declines rather than cancels a request.');
check(BookingService::mayAct('cancel', 'awaiting_handover', true) && BookingService::mayAct('cancel', 'awaiting_handover', false), 'Either side cancels before the handover.');
check(!BookingService::mayAct('cancel', 'in_progress', false), 'Nobody cancels once the item has changed hands.');
$checks += 8;

// The transition map.
foreach ([
    ['requested', 'awaiting_handover', true],
    ['requested', 'rejected', true],
    ['requested', 'in_progress', false],
    ['awaiting_handover', 'in_progress', true],
    ['awaiting_handover', 'completed', false],
    ['in_progress', 'awaiting_return', true],
    ['awaiting_return', 'completed', true],
    ['pending_moderator', 'escalated', true],
    ['completed', 'cancelled', false],
    ['cancelled', 'requested', false],
] as [$from, $to, $allowed]) {
    check(Booking::canMove($from, $to) === $allowed, "Transition $from → $to must be " . ($allowed ? 'allowed' : 'refused') . '.');
    $checks++;
}

// ── Handover (Plan 3.2) ─────────────────────────────────────────────────────

check(isset(HandoverService::photoCountErrors(0)['photos']), 'A handover needs at least one photo.');
check(HandoverService::photoCountErrors(1) === [] && HandoverService::photoCountErrors(5) === [], '1 to 5 photos are fine.');
check(isset(HandoverService::photoCountErrors(6)['photos']), 'Six photos are too many.');
$checks += 3;

$open   = ['lender_accepted_at' => null, 'borrower_accepted_at' => null];
$half   = ['lender_accepted_at' => '2026-03-01 10:00:00', 'borrower_accepted_at' => null];
$locked = ['lender_accepted_at' => '2026-03-01 10:00:00', 'borrower_accepted_at' => '2026-03-01 10:05:00'];
check(HandoverService::sideOpen(null, 'lender', 'awaiting_handover'), 'Before any upload, both sides are open.');
check(!HandoverService::sideOpen($half, 'lender', 'awaiting_handover'), 'A side that accepted cannot edit.');
check(HandoverService::sideOpen($half, 'borrower', 'awaiting_handover'), 'The other side still can.');
check(!HandoverService::sideOpen($locked, 'borrower', 'in_progress'), 'Once both accepted, nothing can change.');
check(!HandoverService::sideOpen($open, 'borrower', 'requested'), 'No handover before the request is accepted.');
$checks += 5;

// ── Return and late fees (Plan 3.3, §7.6) ──────────────────────────────────

$at = static fn (string $when): DateTimeImmutable => new DateTimeImmutable($when);

check(ReturnService::hoursLate('2026-03-05', $at('2026-03-05 23:59:00')) === 0, 'Returned on the end date is on time.');
check(ReturnService::hoursLate('2026-03-05', $at('2026-03-06 00:00:00')) === 0, 'Midnight at the end of the end date is still on time.');
check(ReturnService::hoursLate('2026-03-05', $at('2026-03-06 00:30:00')) === 1, 'Half an hour late counts as one hour.');
check(ReturnService::hoursLate('2026-03-05', $at('2026-03-03 10:00:00')) === 0, 'An early return is on time.');
$checks += 4;

foreach ([
    'on time'   => [0, 'borrower', 0],
    '1 hour'    => [1, 'lender', 0],
    '24 hours'  => [24, 'lender', 0],
    '25 hours'  => [25, 'lender', 40],
    '48 hours'  => [48, 'lender', 40],
    '49 hours'  => [49, 'lender', 80],
    '72 hours'  => [72, 'lender', 80],
    '100 hours' => [100, 'lender', 80],
] as $label => [$hours, $bufferTo, $extra]) {
    $charges = ReturnService::lateCharges($hours, 40);
    check($charges['buffer_to'] === $bufferTo && $charges['extra'] === $extra, 'Late fee wrong at ' . $label . '.');
    $checks++;
}

// The shortfall split: the borrower pays what they have, the Reserve the rest.
check(LedgerService::shortfallSplit(80, 100) === ['paid' => 80, 'covered' => 0], 'Enough points: no cover.');
check(LedgerService::shortfallSplit(80, 30) === ['paid' => 30, 'covered' => 50], 'Short: the Reserve covers 50.');
check(LedgerService::shortfallSplit(80, 0) === ['paid' => 0, 'covered' => 80], 'Empty wallet: the Reserve covers all.');
$checks += 3;

check(ReturnService::photosOpen(null, 'in_progress'), 'Return photos open while the item is out.');
check(!ReturnService::photosOpen(['lender_decision' => 'accepted'], 'awaiting_return'), 'Locked once the lender decides.');
check(!ReturnService::photosOpen(null, 'awaiting_handover'), 'No return before the handover.');
$checks += 3;

// ── Damage claims (Plan 3.4, §10.3) ────────────────────────────────────────

// 20% of the declared value, rounded in the lender's favour.
check(DamageClaimService::simpleCap(1000) === 200, '20% of 1,000 is 200.');
check(DamageClaimService::simpleCap(999) === 200, '20% of 999 rounds up to 200.');
check(DamageClaimService::simpleCap(1) === 1, '20% of 1 rounds up to 1.');
$checks += 3;

check(DamageClaimService::track('minor', 200, 1000, false) === 'simple', 'Minor at the cap is simple.');
check(DamageClaimService::track('minor', 201, 1000, false) === 'moderator', 'Minor over the cap goes to the moderator.');
check(DamageClaimService::track('moderate', 10, 1000, false) === 'moderator', 'Anything but minor goes to the moderator.');
check(DamageClaimService::track('minor', 10, 1000, true) === 'admin', 'A moderator-involved booking goes to the Admin.');
$checks += 4;

check(DamageClaimService::claimErrors('minor', 50, 1000, 'Cracked lid', 1) === [], 'A complete claim passes.');
check(isset(DamageClaimService::claimErrors('minor', 1001, 1000, 'x', 1)['amount']), 'A penalty over the declared value is refused.');
check(isset(DamageClaimService::claimErrors('minor', 0, 1000, 'x', 1)['amount']), 'A zero penalty is refused.');
check(isset(DamageClaimService::claimErrors('scratched', 10, 1000, 'x', 1)['severity']), 'An unknown severity is refused.');
check(isset(DamageClaimService::claimErrors('minor', 10, 1000, '', 1)['description']), 'A claim needs a description.');
check(isset(DamageClaimService::claimErrors('minor', 10, 1000, 'x', 0)['photos']), 'A claim needs evidence.');
$checks += 6;

foreach ([
    ['awaiting_borrower', 'closed', true],
    ['awaiting_borrower', 'pending_moderator', true],
    ['awaiting_borrower', 'resolved', false],
    ['pending_moderator', 'resolved', true],
    ['pending_moderator', 'closed', false],
    ['resolved', 'pending_moderator', false],
] as [$from, $to, $allowed]) {
    check(DamageClaim::canMove($from, $to) === $allowed, "Claim $from → $to must be " . ($allowed ? 'allowed' : 'refused') . '.');
    $checks++;
}

echo 'Passed: ' . $checks . " booking checks — quotes, buffer, dates, who may act, transitions, handover, late fees, claims.\n";
