<?php

declare(strict_types=1);

/**
 * Trust-score rules that need no database (Plan 1.6, §6.3).
 *
 *     C:\xampp\php\php.exe tests\trust.php
 */

require_once __DIR__ . '/../app/autoload.php';

$checks = 0;

function trustCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$blank = [
    'average_stars' => 0.0, 'ratings' => 0, 'completed' => 0, 'on_time_percent' => 100, 'months' => 0,
    'donations' => 0, 'upheld_claims' => 0, 'suspensions' => 0, 'shortfalls' => 0,
];

// No transactions: the community midpoint, whatever else is true.
trustCheck(TrustScoreService::compute($blank)['score'] === 50, 'A newcomer scores 50.');
trustCheck(TrustScoreService::compute(['months' => 30] + $blank)['score'] === 50, 'Tenure alone does not move a score with no transactions.');
$checks += 2;

// Worked example: 10 completed, 4.5★ from 8 ratings, 90% on time, 12 months, 1 donation.
$worked = TrustScoreService::compute([
    'average_stars' => 4.5, 'ratings' => 8, 'completed' => 10, 'on_time_percent' => 90, 'months' => 12,
    'donations' => 1, 'upheld_claims' => 0, 'suspensions' => 0, 'shortfalls' => 0,
] + $blank);
// R 90, V 100, L 90, T 50, C 20 → 36 + 20 + 18 + 5 + 2 = 81; blended (500 + 810) / 20 = 65.5 → 66.
trustCheck($worked['factors'] === ['R' => 90, 'V' => 100, 'L' => 90, 'T' => 50, 'C' => 20], 'Factors of the worked example.');
trustCheck(abs($worked['weighted'] - 81.0) < 0.001, 'Weighted score of the worked example is 81.');
trustCheck($worked['score'] === 66, 'The worked example blends to 66.');
$checks += 3;

// Lots of history: the member's own record dominates.
$veteran = TrustScoreService::compute(['average_stars' => 5.0, 'ratings' => 90, 'completed' => 90, 'months' => 48, 'donations' => 5] + $blank);
trustCheck($veteran['score'] === 95, 'A perfect record over 90 transactions scores 95.');
$checks++;

// Each penalty.
$base = ['average_stars' => 4.5, 'ratings' => 8, 'completed' => 10, 'on_time_percent' => 90, 'months' => 12, 'donations' => 1] + $blank;
trustCheck(TrustScoreService::compute(['upheld_claims' => 1] + $base)['score'] === 61, 'An upheld claim costs 5.');
trustCheck(TrustScoreService::compute(['suspensions' => 1] + $base)['score'] === 56, 'A past suspension costs 10.');
trustCheck(TrustScoreService::compute(['shortfalls' => 2] + $base)['score'] === 56, 'Two shortfall covers cost 10.');
$checks += 3;

// Clamping.
trustCheck(TrustScoreService::compute(['suspensions' => 20] + $base)['score'] === 0, 'Never below 0.');
trustCheck(TrustScoreService::compute(['completed' => 1000, 'average_stars' => 5.0, 'ratings' => 1000, 'months' => 100, 'donations' => 50] + $blank)['score'] <= 100, 'Never above 100.');
trustCheck(TrustScoreService::compute(['months' => 500] + $base)['factors']['T'] === 100, 'Tenure tops out at 100.');
$checks += 3;

trustCheck(array_sum(TrustScoreService::WEIGHTS) === 1.0, 'The weights add up to 1.');
$checks++;

echo 'Passed: ' . $checks . " trust-score checks — midpoint, worked example, penalties, clamping.\n";
