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

require_once __DIR__ . '/../app/autoload.php';

$checks = 0;

function bookingCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// ── Quote ───────────────────────────────────────────────────────────────────

// A one-day booking: start and end on the same day count as one day.
$one = BookingService::quote('2026-03-01', '2026-03-01', 50, null);
bookingCheck($one['days'] === 1 && $one['charge'] === 50 && $one['buffer'] === 50 && $one['total'] === 100, 'One day at 50/day plus a 50 buffer.');
$checks++;

// Daily against monthly: 10 days of 50 is 500; one month of 400 is cheaper.
$ten = BookingService::quote('2026-03-01', '2026-03-10', 50, 400);
bookingCheck($ten['days'] === 10 && $ten['daily_total'] === 500 && $ten['monthly_total'] === 400, '10 days quoted both ways.');
bookingCheck($ten['cheaper'] === 'monthly' && $ten['basis'] === 'monthly' && $ten['charge'] === 400, 'No basis chosen: the cheaper one is used.');
bookingCheck(BookingService::quote('2026-03-01', '2026-03-10', 50, 400, 'daily')['charge'] === 500, 'A chosen basis is honoured.');
bookingCheck(BookingService::quote('2026-03-01', '2026-03-10', 50, null, 'monthly')['basis'] === 'daily', 'An unoffered basis falls back.');
$checks += 4;

// Monthly is charged per started 30 days.
bookingCheck(BookingService::quote('2026-03-01', '2026-03-30', null, 600)['charge'] === 600, '30 days is one month.');
bookingCheck(BookingService::quote('2026-03-01', '2026-03-31', null, 600)['charge'] === 1200, '31 days is two months.');
$checks += 2;

// The 27-day nudge toward monthly (§8.4).
bookingCheck(!BookingService::quote('2026-03-01', '2026-03-27', 10, 200, 'daily')['nudge'], '27 days is not nudged.');
bookingCheck(BookingService::quote('2026-03-01', '2026-03-28', 10, 400, 'daily')['nudge'], '28 days on the daily rate is nudged.');
bookingCheck(!BookingService::quote('2026-03-01', '2026-03-28', 10, null, 'daily')['nudge'], 'No monthly rate, no nudge.');
$checks += 3;

// The late buffer is one daily rate; a monthly-only item uses monthly ÷ 30, rounded up.
bookingCheck(BookingService::lateBuffer(40, 900) === 40, 'Buffer is the daily rate.');
bookingCheck(BookingService::lateBuffer(null, 900) === 30, 'Monthly-only buffer: 900 / 30.');
bookingCheck(BookingService::lateBuffer(null, 901) === 31, 'Monthly-only buffer rounds up.');
$checks += 3;

// Across a month boundary and a leap day.
bookingCheck(BookingService::quote('2028-02-28', '2028-03-01', 10, null)['days'] === 3, 'Leap year: 28 Feb – 1 Mar is 3 days.');
$checks++;

// ── Dates ───────────────────────────────────────────────────────────────────

bookingCheck(BookingService::datesErrors('2026-03-01', '2026-03-05', '2026-03-01') === [], 'Starting today is fine.');
bookingCheck(isset(BookingService::datesErrors('2026-02-28', '2026-03-05', '2026-03-01')['start_date']), 'Starting yesterday is refused.');
bookingCheck(isset(BookingService::datesErrors('2026-03-05', '2026-03-04', '2026-03-01')['end_date']), 'Ending before starting is refused.');
bookingCheck(isset(BookingService::datesErrors('2026-03-01', '2026-12-31', '2026-03-01')['end_date']), 'Over 180 days is refused.');
$checks += 4;

// ── Who may do what, from which status ─────────────────────────────────────

bookingCheck(BookingService::mayAct('accept', 'requested', true), 'The lender accepts a request.');
bookingCheck(!BookingService::mayAct('accept', 'requested', false), 'The borrower cannot accept.');
bookingCheck(!BookingService::mayAct('accept', 'awaiting_handover', true), 'An accepted booking cannot be accepted again.');
bookingCheck(BookingService::mayAct('decline', 'requested', true), 'The lender declines a request.');
bookingCheck(BookingService::mayAct('cancel', 'requested', false), 'The borrower withdraws a request.');
bookingCheck(!BookingService::mayAct('cancel', 'requested', true), 'The lender declines rather than cancels a request.');
bookingCheck(BookingService::mayAct('cancel', 'awaiting_handover', true) && BookingService::mayAct('cancel', 'awaiting_handover', false), 'Either side cancels before the handover.');
bookingCheck(!BookingService::mayAct('cancel', 'in_progress', false), 'Nobody cancels once the item has changed hands.');
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
    bookingCheck(BookingService::canMove($from, $to) === $allowed, "Transition $from → $to must be " . ($allowed ? 'allowed' : 'refused') . '.');
    $checks++;
}

// ── Handover (Plan 3.2) ─────────────────────────────────────────────────────

bookingCheck(isset(HandoverService::photoCountErrors(0)['photos']), 'A handover needs at least one photo.');
bookingCheck(HandoverService::photoCountErrors(1) === [] && HandoverService::photoCountErrors(5) === [], '1 to 5 photos are fine.');
bookingCheck(isset(HandoverService::photoCountErrors(6)['photos']), 'Six photos are too many.');
$checks += 3;

$open   = ['lender_accepted_at' => null, 'borrower_accepted_at' => null];
$half   = ['lender_accepted_at' => '2026-03-01 10:00:00', 'borrower_accepted_at' => null];
$locked = ['lender_accepted_at' => '2026-03-01 10:00:00', 'borrower_accepted_at' => '2026-03-01 10:05:00'];
bookingCheck(HandoverService::sideOpen(null, 'lender', 'awaiting_handover'), 'Before any upload, both sides are open.');
bookingCheck(!HandoverService::sideOpen($half, 'lender', 'awaiting_handover'), 'A side that accepted cannot edit.');
bookingCheck(HandoverService::sideOpen($half, 'borrower', 'awaiting_handover'), 'The other side still can.');
bookingCheck(!HandoverService::sideOpen($locked, 'borrower', 'in_progress'), 'Once both accepted, nothing can change.');
bookingCheck(!HandoverService::sideOpen($open, 'borrower', 'requested'), 'No handover before the request is accepted.');
$checks += 5;

echo 'Passed: ' . $checks . " booking checks — quotes, buffer, dates, who may act, transitions, handover.\n";
