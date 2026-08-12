<?php

declare(strict_types=1);

/**
 * Points pool. Figma "Points Pool" (378:148).
 *
 * @var array $stats  four figures for the stat row
 * @var array $ledger  rows: date, title, meta, amount, amount_class, balance_after
 */

// Sample view data — replaced by the controller once SponsorLiaisonController lands.
$stats ??= [
    ['label' => 'Current balance',    'value' => '42,300', 'note' => 'Sponsor + Aid pools', 'class' => 'primary'],
    ['label' => 'Injected (all time)', 'value' => '+58,900', 'note' => 'From 5 sponsors',    'class' => 'success'],
    ['label' => 'Released via grants', 'value' => '−16,600', 'note' => '41 grants funded',   'class' => 'error'],
    ['label' => 'Net change (Q3)',     'value' => '+7,000',  'note' => 'vs Q2',              'class' => 'success'],
];

$ledger ??= [
    ['date' => '15 Jul', 'title' => 'Purchase injection', 'meta' => 'Northwind Co · INV-0312',        'amount' => '+2,500', 'amount_class' => 'success', 'balance_after' => '43,300'],
    ['date' => '13 Jul', 'title' => 'Aid grant release',  'meta' => 'Grant #A-1042 · M. Lawsan',       'amount' => '−3,000', 'amount_class' => 'error',   'balance_after' => '40,800'],
    ['date' => '09 Jul', 'title' => 'Purchase injection', 'meta' => 'ACM Corp · INV-0306',             'amount' => '+4,500', 'amount_class' => 'success', 'balance_after' => '45,300'],
    ['date' => '05 Jul', 'title' => 'Purchase injection', 'meta' => 'MNM · INV-0298',                  'amount' => '+3,000', 'amount_class' => 'success', 'balance_after' => '48,300'],
];

$pageTitle = 'Points pool';
$navActive = 'points-pool';

include __DIR__ . '/../../../partials/header-sponsor-liaison.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Points pool</h1>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-card">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value" style="color: var(--color-<?= $stat['class'] === 'primary' ? 'primary' : $stat['class'] . '-text' ?>);"><?= e($stat['value']) ?></strong>
            <span class="stat-card__note"><?= e($stat['note']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Pool ledger</h2>
        <a class="link section__action" href="/sponsor-liaison/points-pool/ledger">View all</a>
    </div>

    <ul class="row-list">
        <?php foreach ($ledger as $entry): ?>
            <li class="list-row">
                <span style="width: 70px; flex-shrink: 0; color: var(--color-text-muted); font-size: var(--text-ui-body);"><?= e($entry['date']) ?></span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($entry['title']) ?></span>
                    <span class="list-row__meta"><?= e($entry['meta']) ?></span>
                </div>
                <strong class="list-row__title" style="color: var(--color-<?= e($entry['amount_class']) ?>-text);"><?= e($entry['amount']) ?></strong>
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 2px;">
                    <span class="list-row__title"><?= e($entry['balance_after']) ?></span>
                    <span class="stat-card__note">balance after</span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
