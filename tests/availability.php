<?php

declare(strict_types=1);

/**
 * Availability calendar rules that need no database (Plan 2.4): the date
 * ranges every block and booking request share.
 *
 *     C:\xampp\php\php.exe tests\availability.php
 */

require_once __DIR__ . '/../app/autoload.php';

$checks = 0;

function availabilityCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$today = '2026-03-01';
availabilityCheck(AvailabilityService::rangeErrors('2026-03-01', '2026-03-01', $today) === [], 'A one-day range starting today is fine.');
availabilityCheck(isset(AvailabilityService::rangeErrors('2026-02-28', '2026-03-02', $today)['start_date']), 'A range starting in the past is refused.');
availabilityCheck(isset(AvailabilityService::rangeErrors('2026-03-05', '2026-03-04', $today)['end_date']), 'An end before the start is refused.');
availabilityCheck(isset(AvailabilityService::rangeErrors('2026-02-30', '2026-03-04', $today)['start_date']), 'An impossible date is refused.');
$checks += 4;

echo 'Passed: ' . $checks . " availability checks — date ranges.\n";
