<?php

declare(strict_types=1);

/**
 * Saved-search rules that need no database (Plan 2.3).
 *
 *     C:\xampp\php\php.exe tests\saved-searches.php
 */

require_once __DIR__ . '/../app/autoload.php';

$checks = 0;

function savedSearchCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

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
savedSearchCheck($clean === ['q' => 'drill', 'category' => 'tools-hardware', 'type' => 'rentals', 'community' => 'temporary'], 'Only known filters survive.');
$checks++;

savedSearchCheck(SavedSearchService::cleanFilters(['type' => 'everything', 'community' => 'home', 'category' => '"><script>']) === [], 'Bad filter values are dropped.');
savedSearchCheck(SavedSearchService::cleanFilters(['q' => ['array']]) === [], 'Non-text input is dropped.');
savedSearchCheck(mb_strlen(SavedSearchService::cleanFilters(['q' => str_repeat('a', 500)])['q']) === 100, 'Long search text is cut to 100 characters.');
$checks += 3;

// The 10-search limit, and the name rule.
savedSearchCheck(SavedSearchService::saveErrors('Tools', 9) === [], 'The tenth search may be saved.');
savedSearchCheck(isset(SavedSearchService::saveErrors('Tools', 10)['name']), 'An eleventh search is refused.');
savedSearchCheck(isset(SavedSearchService::saveErrors('', 0)['name']), 'A search needs a name.');
savedSearchCheck(isset(SavedSearchService::saveErrors(str_repeat('n', 81), 0)['name']), 'A name over 80 characters is refused.');
savedSearchCheck(SavedSearchService::saveErrors(str_repeat('n', 80), 0) === [], 'An 80-character name is fine.');
$checks += 5;

echo 'Passed: ' . $checks . " saved-search checks — filter whitelist, name rule, 10-search limit.\n";
