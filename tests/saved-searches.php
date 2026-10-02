<?php

declare(strict_types=1);

/**
 * Saved-search rules that need no database (Plan 2.3).
 *
 *     C:\xampp\php\php.exe tests\saved-searches.php
 */

require_once __DIR__ . '/common.php';

$checks = 0;

// Unknown filters, empty values and bad values are dropped.
$clean = SavedSearchService::cleanFilters([
    'q'         => '  drill ',
    'category'  => 'tools-hardware',
    'type'      => 'rentals',
    'community' => 'temporary',
    'page'      => '3',
    'owner'     => '4',
    'csrf_token' => 'x',
]);
check($clean === ['q' => 'drill', 'category' => 'tools-hardware', 'type' => 'rentals', 'community' => 'temporary'], 'Only known filters survive.');
$checks++;

check(SavedSearchService::cleanFilters(['type' => 'everything', 'community' => 'home', 'category' => '"><script>']) === [], 'Bad filter values are dropped.');
check(SavedSearchService::cleanFilters(['q' => ['array']]) === [], 'Non-text input is dropped.');
check(mb_strlen(SavedSearchService::cleanFilters(['q' => str_repeat('a', 500)])['q']) === 100, 'Long search text is cut to 100 characters.');
$checks += 3;

// The 10-search limit, and the name rule.
check(SavedSearchService::saveErrors('Tools', 9) === [], 'The tenth search may be saved.');
check(isset(SavedSearchService::saveErrors('Tools', 10)['name']), 'An eleventh search is refused.');
check(isset(SavedSearchService::saveErrors('', 0)['name']), 'A search needs a name.');
check(isset(SavedSearchService::saveErrors(str_repeat('n', 81), 0)['name']), 'A name over 80 characters is refused.');
check(SavedSearchService::saveErrors(str_repeat('n', 80), 0) === [], 'An 80-character name is fine.');
$checks += 5;

echo 'Passed: ' . $checks . " saved-search checks — filter whitelist, name rule, 10-search limit.\n";
