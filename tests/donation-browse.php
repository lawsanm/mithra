<?php

declare(strict_types=1);

/** Read-only donation discovery checks against the migrated seed database. */
require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/helpers.php';

$pdo = Database::connection();
$items = new Item($pdo);
$checks = 0;

function donationCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$donations = $items->browse(1, 4, null, '', 1, 'donation');
$titles = array_column($donations, 'title');
foreach (['School Uniform Set (Grade 5)', 'Wooden Study Desk', "Box of Children's Story Books"] as $title) {
    donationCheck(in_array($title, $titles, true), $title . ' must be discoverable in Kollupitiya');
    $checks++;
}
foreach (['Baby Clothes Bundle', "Kids' Bicycle (16 inch)", 'Plastic Chairs ×6', 'Rice Cooker (1 L)'] as $title) {
    donationCheck(!in_array($title, $titles, true), 'Own, donated, pending or other-division items must stay excluded: ' . $title);
    $checks++;
}
foreach ([null, 'rental', 'donation'] as $type) {
    $rows = $items->browse(1, 4, null, '', 1, $type);
    donationCheck(count($rows) === $items->countBrowse(1, 4, null, '', $type), 'Count must match the selected type');
    donationCheck($type === null || array_unique(array_column($rows, 'listing_type')) === [$type], 'Listing types must not mix');
    $checks += 2;
}
$desk = $items->browse(1, 4, null, 'Wooden Study Desk', 1, 'donation');
donationCheck(count($desk) === 1 && !empty($desk[0]['photo']), 'Donation search must return the matching photographed item');
$checks++;

session_start();
$_SESSION = ['user_id' => 4, 'role' => 'member', 'csrf_token' => bin2hex(random_bytes(32))];
$_GET = ['type' => 'donations', 'q' => 'Wooden Study Desk'];
ob_start();
try {
    (new ItemController($pdo))->browse();
    $body = (string) ob_get_contents();
} finally {
    ob_end_clean();
    $_SESSION = [];
    session_destroy();
}
donationCheck(str_contains($body, 'Free — donation') && !str_contains($body, 'Rate not set'), 'Donations must be labelled free');
donationCheck(str_contains($body, 'name="type" value="donations"'), 'Search must retain the donation filter');
donationCheck(str_contains($body, 'q=Wooden+Study+Desk&amp;category=') && str_contains($body, '&amp;type=donations'), 'Category links must preserve search and listing type');
donationCheck(str_contains($body, 'item-photos%2Fdemo%2Fstudy-desk.jpg'), 'The matching item image must render');
$checks += 4;

echo 'Passed ' . $checks . " donation browse checks.\n";
