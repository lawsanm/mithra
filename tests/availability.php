<?php

declare(strict_types=1);

/**
 * Availability calendar rules that need no database (Plan 2.4). The overlap
 * rule is the same one booking requests use.
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

// [a start, a end, b start, b end, overlaps?]
foreach ([
    'apart'                => ['2026-03-01', '2026-03-05', '2026-03-10', '2026-03-12', false],
    'touching end to start' => ['2026-03-01', '2026-03-05', '2026-03-05', '2026-03-08', true],
    'next day'             => ['2026-03-01', '2026-03-05', '2026-03-06', '2026-03-08', false],
    'same single day'      => ['2026-03-04', '2026-03-04', '2026-03-04', '2026-03-04', true],
    'one inside another'   => ['2026-03-01', '2026-03-31', '2026-03-10', '2026-03-12', true],
    'one around another'   => ['2026-03-10', '2026-03-12', '2026-03-01', '2026-03-31', true],
    'partial overlap'      => ['2026-03-01', '2026-03-10', '2026-03-08', '2026-03-20', true],
    'across a month'       => ['2026-02-27', '2026-03-02', '2026-03-01', '2026-03-01', true],
] as $label => [$aStart, $aEnd, $bStart, $bEnd, $expected]) {
    availabilityCheck(AvailabilityService::overlaps($aStart, $aEnd, $bStart, $bEnd) === $expected, 'Overlap wrong: ' . $label);
    availabilityCheck(AvailabilityService::overlaps($bStart, $bEnd, $aStart, $aEnd) === $expected, 'Overlap not symmetric: ' . $label);
    $checks += 2;
}

$today = '2026-03-01';
availabilityCheck(AvailabilityService::rangeErrors('2026-03-01', '2026-03-01', $today) === [], 'A one-day range starting today is fine.');
availabilityCheck(isset(AvailabilityService::rangeErrors('2026-02-28', '2026-03-02', $today)['start_date']), 'A range starting in the past is refused.');
availabilityCheck(isset(AvailabilityService::rangeErrors('2026-03-05', '2026-03-04', $today)['end_date']), 'An end before the start is refused.');
availabilityCheck(isset(AvailabilityService::rangeErrors('2026-02-30', '2026-03-04', $today)['start_date']), 'An impossible date is refused.');
$checks += 4;

echo 'Passed: ' . $checks . " availability checks — inclusive overlaps and date ranges.\n";
