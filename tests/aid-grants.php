<?php

declare(strict_types=1);

/**
 * Aid-grant rules that need no database (Plan 4.4, §12.1).
 *
 *     C:\xampp\php\php.exe tests\aid-grants.php
 */

require_once __DIR__ . '/common.php';

$checks = 0;

$now = new DateTimeImmutable('2026-10-02 12:00:00');

// One live grant at a time.
check(AidGrantService::eligibilityErrors(0, null, 0, $now) === [], 'A first request is allowed.');
check(isset(AidGrantService::eligibilityErrors(1, null, 0, $now)['form']), 'A second live grant is refused.');
$checks += 2;

// The 60-day cooling period after a grant.
check(isset(AidGrantService::eligibilityErrors(0, '2026-08-10 12:00:00', 0, $now)['form']), '53 days after a grant is too soon.');
check(AidGrantService::eligibilityErrors(0, '2026-08-03 12:00:00', 0, $now) === [], '60 days after a grant is fine.');
$checks += 2;

// The yearly cap.
check(isset(AidGrantService::eligibilityErrors(0, null, 500, $now)['form']), 'A member at the cap cannot ask.');
check(AidGrantService::requestErrors(250, 'School supplies', 'Books', 250) === [], 'Up to the remaining 250 is fine.');
check(isset(AidGrantService::requestErrors(251, 'School supplies', 'Books', 250)['amount']), 'Over the remaining amount is refused.');
check(isset(AidGrantService::requestErrors(0, 'School supplies', 'Books', 0)['amount']), 'Nothing is not a request.');
$checks += 4;

// The purpose list and the details.
check(isset(AidGrantService::requestErrors(50, 'A holiday', 'Fun', 0)['purpose']), 'A purpose off the list is refused.');
check(isset(AidGrantService::requestErrors(50, 'Medical costs', '', 0)['details']), 'A request needs details.');
$checks += 2;

check(AidGrantService::YEARLY_CAP === 500 && AidGrantService::COOLING_DAYS === 60, 'Limits match Plan §12.1.');
check(!in_array('rejected_moderator', AidGrant::LIVE_STATES, true) && !in_array('rejected_liaison', AidGrant::LIVE_STATES, true), 'A rejected grant is not live (I6).');
$checks += 2;

echo 'Passed: ' . $checks . " aid-grant checks — one live grant, 60-day cooling, yearly cap, purpose list.\n";
