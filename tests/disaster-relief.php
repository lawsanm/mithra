<?php

declare(strict_types=1);

/**
 * Relief record rules that need no database (Plan §14.1 step 5): what a
 * submitted relief form becomes, and which values are refused.
 *
 *     C:\xampp\php\php.exe tests\disaster-relief.php
 *
 * The division check, the active-disaster check and the lock once a disaster
 * ends read from MySQL and are exercised against a running local database.
 */

require_once __DIR__ . '/common.php';

$checks = 0;

const TODAY = '2026-07-20';
const START = '2026-07-12';

/**
 * @param array<string, string> $input
 *
 * @return array<string, string> field => message, empty when the input is accepted
 */
function refusals(array $input): array
{
    try {
        DisasterReliefService::record($input, TODAY, START);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

$valid = [
    'relief_type'        => 'food',
    'description'        => '  40 dry-ration packs  ',
    'location'           => ' Temple Road hall ',
    'households_reached' => '12',
    'estimated_value'    => '25000',
    'distributed_on'     => '2026-07-15',
    'sponsor_id'         => '3',
    'notes'              => 'Second round due Friday.',
];

// A complete form is stored with the database's column names, trimmed and typed.
$record = DisasterReliefService::record($valid, TODAY, START);
check($record['description'] === '40 dry-ration packs', 'Description must be trimmed.');
check($record['location'] === 'Temple Road hall', 'Location must be trimmed.');
check($record['households_reached'] === 12, 'Households must be an integer.');
check($record['estimated_value'] === 25000, 'Estimated value must be an integer.');
check($record['sponsor_id'] === 3, 'Sponsor must be an integer id.');
check(array_keys($record) === ['sponsor_id', 'relief_type', 'description', 'location', 'households_reached',
    'estimated_value', 'distributed_on', 'notes'], 'Record must hold exactly the stored columns.');
$checks += 6;

// Optional fields left empty are stored as NULL, not as empty strings or 0.
$minimal = DisasterReliefService::record(['sponsor_id' => '', 'estimated_value' => ' ', 'notes' => ''] + $valid, TODAY, START);
foreach (['sponsor_id', 'estimated_value', 'notes'] as $column) {
    check($minimal[$column] === null, "Empty $column must be stored as NULL.");
    $checks++;
}

// Every relief type the form offers is accepted; nothing else is.
foreach (array_keys(DisasterReliefService::RELIEF_TYPES) as $type) {
    check(refusals(['relief_type' => $type] + $valid) === [], "$type must be accepted.");
    $checks++;
}
check(array_keys(DisasterReliefService::RELIEF_TYPES) === ['food', 'water', 'shelter', 'medical', 'clothing', 'cash', 'other'],
    'Types must match the migration ENUM.');
foreach (['', 'Food', 'fuel', 'food '] as $type) {
    check(isset(refusals(['relief_type' => $type] + $valid)['relief_type']), "Type '$type' must be refused.");
    $checks++;
}
$checks++;

// Required text and a positive household count.
check(isset(refusals(['description' => '   '] + $valid)['description']), 'Blank description must be refused.');
foreach (['40', '12-5', '100 !!'] as $numbersOnly) {
    check(isset(refusals(['description' => $numbersOnly] + $valid)['description']), "Description '$numbersOnly' must be refused.");
}
foreach (['Rice', '5kg of rice', 'සහල් කිලෝ 10', 'அரிசி 5 கிலோ'] as $words) {
    check(refusals(['description' => $words] + $valid) === [], "Description '$words' must be accepted.");
}
check(isset(refusals(['location' => ''] + $valid)['location']), 'Blank location must be refused.');
check(isset(refusals(['households_reached' => '0'] + $valid)['households_reached']), 'Zero households must be refused.');
$checks += 3;

// The date must be real, not in the future, and not before the disaster began.
foreach (['', '15/07/2026', '2026-02-30', '2026-7-15'] as $date) {
    check(isset(refusals(['distributed_on' => $date] + $valid)['distributed_on']), "Date '$date' must be refused.");
    $checks++;
}
check(isset(refusals(['distributed_on' => '2026-07-21'] + $valid)['distributed_on']), 'A future date must be refused.');
check(isset(refusals(['distributed_on' => '2026-07-11'] + $valid)['distributed_on']), 'A date before the disaster must be refused.');
check(refusals(['distributed_on' => START] + $valid) === [], 'The first day of the disaster must be accepted.');
check(refusals(['distributed_on' => TODAY] + $valid) === [], 'Today must be accepted.');
$checks += 4;

// The report totals: overall, by type and by sponsor, largest group first.
$summary = DisasterReliefService::summarise([
    ['relief_type' => 'food',  'households_reached' => '12', 'estimated_value' => '25000', 'sponsor_id' => '2', 'sponsor_name' => 'Ceylon Fresh Mart'],
    ['relief_type' => 'water', 'households_reached' => '20', 'estimated_value' => null,    'sponsor_id' => null, 'sponsor_name' => null],
    ['relief_type' => 'food',  'households_reached' => '5',  'estimated_value' => '4000',  'sponsor_id' => '2', 'sponsor_name' => 'Ceylon Fresh Mart'],
    ['relief_type' => 'shelter', 'households_reached' => '3', 'estimated_value' => '9000', 'sponsor_id' => '3', 'sponsor_name' => 'Sunrise Pharmacy'],
]);
check($summary['records'] === 4 && $summary['households'] === 40 && $summary['value'] === 38000, 'Totals must add up every record.');
check($summary['sponsors'] === 2, 'Relief with no sponsor must not count as a sponsor.');
check(array_column($summary['by_type'], 'label') === ['Drinking water', 'Food and dry rations', 'Shelter and bedding'],
    'Types must be grouped and ordered by households reached.');
check($summary['by_type'][1] === ['label' => 'Food and dry rations', 'records' => 2, 'households' => 17, 'value' => 29000],
    'A type group must sum its records.');
check(array_column($summary['by_sponsor'], 'label') === ['Not from a sponsor', 'Ceylon Fresh Mart', 'Sunrise Pharmacy'],
    'Relief with no sponsor must be its own group.');
$empty = DisasterReliefService::summarise([]);
check($empty['records'] === 0 && $empty['by_type'] === [] && $empty['sponsors'] === 0, 'An empty report must total zero.');
$checks += 6;

echo "disaster relief: $checks checks passed\n";
