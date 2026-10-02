<?php

declare(strict_types=1);

/**
 * Rating rules that need no database (Plan 3.5).
 *
 *     C:\xampp\php\php.exe tests\ratings.php
 *
 * "Once per record" is also enforced by the unique keys of migration 026.
 */

require_once __DIR__ . '/../app/autoload.php';

$checks = 0;

function ratingCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$booking = static fn (string $status): array => ['borrower_id' => 3, 'lender_id' => 2, 'status' => $status];

// Who may rate what, and in which context.
ratingCheck(RatingService::subject('booking', $booking('completed'), 3) === ['ratee' => 2, 'context' => 'rental'], 'The borrower rates the lender after a rental.');
ratingCheck(RatingService::subject('booking', $booking('completed'), 2) === ['ratee' => 3, 'context' => 'rental'], 'The lender rates the borrower.');
ratingCheck(RatingService::subject('booking', $booking('cancelled'), 3)['context'] === 'cancellation', 'A cancellation can be rated.');
ratingCheck(RatingService::subject('booking', $booking('auto_cancelled'), 2)['context'] === 'cancellation', 'An auto-cancellation can be rated.');
ratingCheck(is_string(RatingService::subject('booking', $booking('in_progress'), 3)), 'A running booking cannot be rated.');
ratingCheck(is_string(RatingService::subject('booking', $booking('rejected'), 3)), 'A declined request cannot be rated.');
ratingCheck(is_string(RatingService::subject('booking', $booking('completed'), 9)), 'Someone outside the booking cannot rate it.');
ratingCheck(is_string(RatingService::subject('booking', null, 3)), 'A missing record cannot be rated.');
$checks += 8;

ratingCheck(RatingService::subject('donation', ['donor_id' => 4, 'recipient_id' => 29, 'status' => 'completed'], 29) === ['ratee' => 4, 'context' => 'donation'], 'The recipient rates the donor.');
ratingCheck(is_string(RatingService::subject('donation', ['donor_id' => 4, 'recipient_id' => 29, 'status' => 'recipient_selected'], 29)), 'A donation is rated once it completes.');
ratingCheck(RatingService::subject('gift', ['sender_id' => 4, 'recipient_id' => 2], 2) === ['ratee' => 4, 'context' => 'gift'], 'A gift can be rated by its recipient.');
$checks += 3;

// Input rules.
ratingCheck(RatingService::inputErrors(5, 'Great', ['smooth']) === [], 'A normal rating passes.');
ratingCheck(isset(RatingService::inputErrors(0, '', [])['rating']), 'Zero stars is refused.');
ratingCheck(isset(RatingService::inputErrors(6, '', [])['rating']), 'Six stars is refused.');
ratingCheck(isset(RatingService::inputErrors(4, str_repeat('a', 501), [])['review']), 'A review over 500 characters is refused.');
ratingCheck(RatingService::inputErrors(4, str_repeat('a', 500), []) === [], 'A 500-character review is fine.');
ratingCheck(isset(RatingService::inputErrors(4, '', ['made-up'])['tags']), 'Unknown tags are refused.');
$checks += 6;

// The 7-day window.
$now = new DateTimeImmutable('2026-03-10 12:00:00');
ratingCheck(RatingService::stillEditable('2026-03-03 12:00:00', $now), 'Exactly 7 days is still editable.');
ratingCheck(!RatingService::stillEditable('2026-03-03 11:59:00', $now), 'Past 7 days is locked.');
$checks += 2;

echo 'Passed: ' . $checks . " rating checks — who may rate what, once per record, input rules, the 7-day window.\n";
