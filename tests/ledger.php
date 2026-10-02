<?php

declare(strict_types=1);

/**
 * Point-ledger invariants against the local database (Plan §7.4).
 *
 *     C:\xampp\php\php.exe tests\ledger.php
 *
 * Every movement runs inside one transaction that is rolled back at the end,
 * so the database is left exactly as it was. Two facts must hold after each
 * kind of movement:
 *
 *   - SUM(point_pools.balance) is unchanged — points move, never appear;
 *   - the change in the 'member_wallets' pool equals the change in
 *     SUM(member_wallets.balance) — the pool mirrors the wallets.
 *
 * The seed data is not itself balanced, so the checks compare before and after
 * rather than absolute figures.
 */

require_once __DIR__ . '/../app/autoload.php';

$checks = 0;

function ledgerCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo     = Database::connection();
$pools   = new PointPool($pdo);
$wallets = new Wallet($pdo);
$ledger  = new LedgerService($pdo, new PointLedger($pdo), $pools, $wallets);

/** @return array{total: int, mirror: int, wallets: int} */
$snapshot = static function () use ($pdo, $pools): array {
    $sum = (int) $pdo->query('SELECT COALESCE(SUM(balance), 0) FROM member_wallets')->fetchColumn();

    return ['total' => $pools->totalBalance(), 'mirror' => $pools->balance('member_wallets'), 'wallets' => $sum];
};

// Two active members with wallets, from the seed.
$a = 2;
$b = 3;

$pdo->beginTransaction();

try {
    // Give both enough to move, from the Sponsor Pool.
    $pdo->exec("UPDATE point_pools SET balance = balance + 5000 WHERE pool_code IN ('sponsor','reserve')");
    $before = $snapshot();

    $movements = [
        'pool to member'   => fn (): int => $ledger->poolToMember('sponsor', $a, 300, 'community_reward'),
        'member to pool'   => fn (): int => $ledger->memberToPool($a, 'in_flight', 120, 'rental_charge', ['booking_id' => 1]),
        'pool back'        => fn (): int => $ledger->poolToMember('in_flight', $b, 120, 'rental_payout', ['booking_id' => 1]),
        'member to member' => fn (): int => $ledger->memberToMember($a, $b, 25, 'gift'),
        'charge + cover'   => fn (): array => $ledger->chargeWithCover($b, $a, $wallets->balance($b) + 40, 'late_fee', 1),
    ];

    foreach ($movements as $label => $move) {
        $start = $snapshot();
        $move();
        $end = $snapshot();

        ledgerCheck($end['total'] === $start['total'], $label . ' changed the total of all pools.');
        ledgerCheck(
            $end['mirror'] - $start['mirror'] === $end['wallets'] - $start['wallets'],
            $label . ' let the member_wallets pool drift from the wallets.'
        );
        $checks += 2;
    }

    // The link is written: an In-Flight hold can be traced to its booking.
    $linked = (int) $pdo->query(
        "SELECT COUNT(*) FROM point_ledger WHERE reason = 'rental_charge' AND booking_id = 1 AND from_user_id = 2"
    )->fetchColumn();
    ledgerCheck($linked >= 1, 'A booking movement must carry its booking_id (I1).');
    $checks++;

    // A wallet never goes below zero: the shortfall split took only what was there.
    ledgerCheck($wallets->lockBalance($b) === 0, 'A charge bigger than the wallet must empty it, not overdraw it.');
    $checks++;

    // Refusals happen before anything is written.
    try {
        $ledger->memberToMember($a, $b, 100000000, 'gift');
        ledgerCheck(false, 'An overdraft must be refused.');
    } catch (InsufficientPointsException $expected) {
        $checks++;
    }

    $after = $snapshot();
    ledgerCheck($after['total'] === $before['total'], 'The run as a whole must not create or destroy points.');
    $checks++;
} finally {
    $pdo->rollBack();
}

// Outside a transaction nothing may move at all.
try {
    $ledger->poolToMember('sponsor', $a, 1, 'community_reward');
    ledgerCheck(false, 'A movement outside a transaction must be refused.');
} catch (LogicException $expected) {
    $checks++;
}

echo 'Passed: ' . $checks . " ledger checks — totals unchanged, wallets mirrored, links written. (Rolled back.)\n";
