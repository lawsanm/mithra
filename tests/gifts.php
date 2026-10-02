<?php

declare(strict_types=1);

/**
 * Gifting rules that need no database (Plan 4.5, §11.1).
 *
 *     C:\xampp\php\php.exe tests\gifts.php
 *
 * The blocking conditions (pending claim, open dispute, overdue return, other
 * division, gifts turned off) are checked in GiftService::send() against
 * MySQL, under the sender's wallet lock.
 */

require_once __DIR__ . '/common.php';

$checks = 0;

check(GiftService::amountErrors(50, 0, 0) === [], 'A small gift passes.');
check(GiftService::amountErrors(200, 0, 0) === [], 'Exactly the daily cap passes.');
check(isset(GiftService::amountErrors(201, 0, 0)['amount']), 'One over the daily cap is refused.');
check(isset(GiftService::amountErrors(60, 150, 150)['amount']), '150 sent today plus 60 is over the cap.');
check(GiftService::amountErrors(50, 150, 150) === [], '150 plus 50 is exactly the cap.');
$checks += 5;

check(GiftService::amountErrors(100, 0, 1900) === [], 'Exactly the yearly cap passes.');
check(isset(GiftService::amountErrors(101, 0, 1900)['amount']), 'One over the yearly cap is refused.');
check(isset(GiftService::amountErrors(0, 0, 0)['amount']), 'A gift of nothing is refused.');
$checks += 3;

check(GiftService::reasonErrors('For the birthday') === [], 'A short reason passes.');
check(isset(GiftService::reasonErrors('')['reason']), 'A gift needs a reason.');
check(GiftService::reasonErrors(str_repeat('r', 100)) === [], '100 characters is fine.');
check(isset(GiftService::reasonErrors(str_repeat('r', 101))['reason']), '101 characters is too long.');
$checks += 4;

check(Gift::DAILY_CAP === 200 && Gift::ANNUAL_CAP === 2000, 'Caps match Plan §11.1.');
$checks++;

echo 'Passed: ' . $checks . " gift checks — daily and yearly caps, reason length.\n";
