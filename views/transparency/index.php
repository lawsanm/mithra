<?php

declare(strict_types=1);

/**
 * Transparency dashboard. Figma: "Transparency Dashboard" (97:169).
 *
 * @var array $pools         six pool balances: label, value, note
 * @var array $invariant     nightly check badge and last-run line
 * @var array $contributions recent sponsor contributions: name, split, amount, date
 */

// Sample view data — replaced by the controller once TransparencyController lands.
$pools ??= [
    ['label' => 'Sponsor Pool',   'value' => '48,200 pts',  'note' => 'welcome bonuses, stipends and community rewards'],
    ['label' => 'Aid Pool',       'value' => '12,750 pts',  'note' => 'grants for essential needs'],
    ['label' => 'Reserve Pool',   'value' => '6,400 pts',   'note' => 'covers shortfalls so no balance goes negative'],
    ['label' => 'In-Flight Pool', 'value' => '3,180 pts',   'note' => 'rental charges + late-fee buffers · 41 bookings'],
    ['label' => 'Retired Pool',   'value' => '1,140 pts',   'note' => 'Type A closures, awaiting recycling'],
    ['label' => 'Member Wallets', 'value' => '121,300 pts', 'note' => 'across 2,412 wallets'],
];

$invariant ??= [
    'badge' => 'Nightly invariant check passed',
    'line'  => 'Last run: 17 Jul 2026, 02:00  ·  sum of the six pools = total points created from sponsor contributions',
];

$contributions ??= [
    [
        'name'   => 'Lanka Hardware (Pvt) Ltd',
        'split'  => 'General 7,000  ·  Aid 3,000',
        'amount' => '10,000 pts',
        'date'   => '14 Jul 2026',
    ],
    [
        'name'   => 'Ceylon Fresh Mart',
        'split'  => 'General 2,500  ·  Aid 2,500',
        'amount' => '5,000 pts',
        'date'   => '30 Jun 2026',
    ],
    [
        'name'   => 'Sunrise Pharmacy',
        'split'  => 'Aid 2,500',
        'amount' => '2,500 pts',
        'date'   => '12 Jun 2026',
    ],
];

$pageTitle = 'Transparency dashboard';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="page-header__title">Transparency Dashboard</h1>

<p class="record-meta">
    Every point in the system, accounted for. Updated live, checked nightly.
</p>

<div class="pool-grid">
    <?php foreach ($pools as $pool): ?>
        <div class="pool-card">
            <span class="pool-card__label"><?= e($pool['label']) ?></span>
            <strong class="pool-card__value"><?= e($pool['value']) ?></strong>
            <span class="pool-card__note"><?= e($pool['note']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<section class="panel">
    <h2 class="visually-hidden">Accounting invariant</h2>
    <div class="media">
        <span class="badge badge--success">
            <span aria-hidden="true">✓</span>
            <?= e($invariant['badge']) ?>
        </span>
        <span class="media__meta"><?= e($invariant['line']) ?></span>
    </div>
</section>

<h2 class="section-heading">Recent sponsor contributions</h2>

<ul class="row-list">
    <?php foreach ($contributions as $contribution): ?>
        <li class="txn-row">
            <div class="txn-row__body">
                <span class="txn-row__name"><?= e($contribution['name']) ?></span>
                <span class="txn-row__note"><?= e($contribution['split']) ?></span>
            </div>
            <span class="txn-row__amount">
                <span class="txn-row__value txn-row__value--in"><?= e($contribution['amount']) ?></span>
                <span class="txn-row__date"><?= e($contribution['date']) ?></span>
            </span>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
