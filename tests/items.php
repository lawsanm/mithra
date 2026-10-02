<?php

declare(strict_types=1);

/**
 * Listing rules that need no database: the declared-value proof tiers of
 * Plan §9.1.
 *
 *     C:\xampp\php\php.exe tests\items.php
 *
 * The review decision itself (approve / adjust / reject, who may decide, the
 * audit trail) writes to MySQL and is exercised against a running local
 * database.
 */

require_once __DIR__ . '/common.php';

$checks = 0;

$proof = 'value-proofs/0123456789abcdef0123456789abcdef.jpg';

// Up to 2,000 points: photos and a description are enough.
foreach ([1, 500, 2000] as $value) {
    check(ItemService::proofErrors($value, null, null) === [], "$value pts must need no proof.");
    $checks++;
}

// 2,001 – 10,000: a receipt, warranty card or retail price reference, with the document.
foreach ([2001, 5000, 10000] as $value) {
    check(isset(ItemService::proofErrors($value, null, null)['value_proof_type']), "$value pts must ask for proof.");
    check(isset(ItemService::proofErrors($value, 'inspection', null)['value_proof_type']), "$value pts is not an inspection tier.");
    check(isset(ItemService::proofErrors($value, 'receipt', null)['value_proof']), "$value pts needs the receipt itself.");
    foreach (['receipt', 'warranty', 'price_reference'] as $type) {
        check(ItemService::proofErrors($value, $type, $proof) === [], "$type must satisfy $value pts.");
        $checks++;
    }
    $checks += 3;
}

// Above 10,000: a receipt or warranty card, or an in-person inspection.
foreach ([10001, 50000] as $value) {
    check(ItemService::proofErrors($value, 'inspection', null) === [], "Inspection must satisfy $value pts.");
    check(ItemService::proofErrors($value, 'receipt', $proof) === [], "A receipt must satisfy $value pts.");
    check(ItemService::proofErrors($value, 'warranty', $proof) === [], "A warranty card must satisfy $value pts.");
    check(isset(ItemService::proofErrors($value, 'price_reference', $proof)['value_proof_type']), "A price reference is not enough above 10,000 pts.");
    check(isset(ItemService::proofErrors($value, null, null)['value_proof_type']), "$value pts must ask for proof.");
    $checks += 5;
}

check(ItemService::PROOF_FREE_UP_TO === 2000 && ItemService::DOCUMENT_UP_TO === 10000, 'Thresholds must match Plan §9.1.');
$checks++;

echo 'Passed: ' . $checks . " listing checks — declared-value proof tiers.\n";
