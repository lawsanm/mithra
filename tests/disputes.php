<?php

declare(strict_types=1);

/**
 * Dispute rules that need no database (Plan 3.6, §10.4, §19).
 *
 *     C:\xampp\php\php.exe tests\disputes.php
 */

require_once __DIR__ . '/../app/autoload.php';

$checks = 0;

function disputeCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$now      = new DateTimeImmutable('2026-03-10 12:00:00');
$accepted = ['status' => 'closed', 'borrower_response' => 'accepted', 'responded_at' => '2026-03-05 12:00:00', 'met_at' => null, 'resolution_closed_at' => null];
$recorded = ['status' => 'pending_moderator', 'borrower_response' => 'contested', 'responded_at' => '2026-03-01 12:00:00', 'met_at' => '2026-03-08 10:00:00', 'resolution_closed_at' => null];

disputeCheck(DisputeService::route($accepted, 0, $now) === 'accepted', 'An accepted simple-path claim can be disputed within 7 days.');
disputeCheck(DisputeService::route(['responded_at' => '2026-03-03 12:00:00'] + $accepted, 0, $now) === 'accepted', 'Exactly 7 days is still inside the window.');
disputeCheck(DisputeService::route(['responded_at' => '2026-03-03 11:00:00'] + $accepted, 0, $now) !== 'accepted', 'Past 7 days it is closed.');
$checks += 3;

disputeCheck(DisputeService::route($recorded, 0, $now) === 'resolution', 'A recorded resolution can be refused with a dispute.');
disputeCheck(DisputeService::route(['met_at' => null] + $recorded, 0, $now) !== 'resolution', 'Nothing to refuse before the moderator records an outcome.');
disputeCheck(DisputeService::route(['resolution_closed_at' => '2026-03-09 10:00:00'] + $recorded, 0, $now) !== 'resolution', 'A signed resolution is final.');
$checks += 3;

disputeCheck(DisputeService::route($accepted, 1, $now) === 'This claim already has an open dispute.', 'One open dispute per claim.');
disputeCheck(DisputeService::route(null, 0, $now) !== 'accepted', 'No claim, no dispute.');
disputeCheck(DisputeService::route(['status' => 'closed', 'borrower_response' => null] + $accepted, 0, $now) !== 'accepted', 'A withdrawn claim cannot be disputed.');
$checks += 3;

echo 'Passed: ' . $checks . " dispute checks — the 7-day window, refusing a resolution, one open dispute per claim.\n";
