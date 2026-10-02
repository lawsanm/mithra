<?php

declare(strict_types=1);

/**
 * Donation request rules that need no database (Plan 2.5, §13.1).
 *
 *     C:\xampp\php\php.exe tests\donations.php
 *
 * First-come selection and "one recipient only" run inside DonationService's
 * locked transaction against MySQL; this file checks who may ask at all.
 */

require_once __DIR__ . '/common.php';

$checks = 0;

$open = ['donor_id' => 7, 'status' => 'open', 'item_status' => 'active', 'division_id' => 1];

check(DonationService::requestErrors($open, 9, [1], null) === [], 'A member of the division may ask.');
check(DonationService::requestErrors($open, 9, [3, 1], null) === [], 'A temporary member of the division may ask.');
check(DonationService::requestErrors($open, 9, [1], 'withdrawn') === [], 'A member who withdrew may ask again.');
$checks += 3;

foreach ([
    'own donation'          => [$open, 7, [1], null],
    'another division'      => [$open, 9, [3], null],
    'already asked'         => [$open, 9, [1], 'pending'],
    'already chosen'        => [$open, 9, [1], 'selected'],
    'recipient chosen'      => [['status' => 'recipient_selected'] + $open, 9, [1], null],
    'completed'             => [['status' => 'completed'] + $open, 9, [1], null],
    'listing paused'        => [['item_status' => 'paused'] + $open, 9, [1], null],
] as $label => [$donation, $member, $divisions, $existing]) {
    check(isset(DonationService::requestErrors($donation, $member, $divisions, $existing)['form']), 'Must refuse: ' . $label);
    $checks++;
}

check(DonationService::MODES === ['donor_chooses', 'first_come'], 'Two selection modes (§13.1).');
$checks++;

echo 'Passed: ' . $checks . " donation checks — who may request, one request each, own donation refused.\n";
