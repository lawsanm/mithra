<?php

declare(strict_types=1);

/**
 * Sponsor CSR report. Figma: "CSR Report" (385:147).
 *
 * @var array $stats     four figures for the stat row
 * @var array $quarters   rows: label, meta, amount
 * @var string $reconcileNote
 */

// Sample view data — replaced by the controller once SponsorController lands.
$stats ??= [
    ['label' => 'Total donated',       'value' => 'LKR 16,000', 'note' => 'All time: LKR 34,500 since 2024', 'primary' => true],
    ['label' => 'Points generated',    'value' => '16,000',     'note' => '1 : 1 — nothing withheld'],
    ['label' => 'Purchases this year', 'value' => '2',          'note' => 'Next: on request'],
    ['label' => 'Aid grants funded',   'value' => '9',          'note' => 'From your Aid Pool share'],
];

$quarters ??= [
    ['label' => 'Q3 2026 (so far)', 'meta' => 'INV-0312 · 70% Sponsor / 30% Aid', 'amount' => 'LKR 10,000'],
    ['label' => 'Q2 2026',          'meta' => 'INV-0248 · 50% Sponsor / 50% Aid', 'amount' => 'LKR 6,000'],
    ['label' => 'Q4 2025',          'meta' => 'INV-0198 · 100% Aid',             'amount' => 'LKR 6,500'],
];

$reconcileNote ??= 'Figures reconcile with the public Transparency Dashboard and the append-only '
                  . 'ledger. Quarterly print-friendly reports are prepared by your Sponsor Liaison.';

$pageTitle = 'CSR report';
$navActive = 'csr-reports';

include __DIR__ . '/../../../partials/header-sponsor.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Your CSR report</h1>
    <button class="btn btn--ghost page-header__action" type="button" disabled title="Coming soon">Download PDF</button>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-card">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value<?= !empty($stat['primary']) ? ' stat-card__value--primary' : '' ?>"><?= e($stat['value']) ?></strong>
            <span class="stat-card__note"><?= e($stat['note']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Quarterly breakdown</h2>
    </div>

    <ul class="row-list">
        <?php foreach ($quarters as $quarter): ?>
            <li class="txn-row">
                <span class="txn-row__icon">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-package"></use></svg>
                </span>
                <div class="txn-row__body">
                    <span class="txn-row__name"><?= e($quarter['label']) ?></span>
                    <span class="txn-row__note"><?= e($quarter['meta']) ?></span>
                </div>
                <span class="txn-row__amount">
                    <span class="txn-row__value"><?= e($quarter['amount']) ?></span>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<p class="page-intro__meta"><?= e($reconcileNote) ?></p>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
