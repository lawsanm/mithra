<?php

declare(strict_types=1);

/**
 * Quarterly CSR impact report — print view. Figma
 * "Quarterly Report — Print View" (383:121).
 *
 * @var array  $report        quarter, prepared_by, prepared_at
 * @var array  $stats         four figures: label, value
 * @var array  $contributions rows: sponsor, receipt, amount
 * @var string $footnote
 */

// Sample view data — replaced by the controller once SponsorLiaisonController lands.
$report ??= [
    'quarter'     => 'Q2 2026 (Apr – Jun)',
    'prepared_by' => 'Sponsor Liaison A. Akalvily',
    'prepared_at' => '20 Jul 2026',
];

$stats ??= [
    ['label' => 'Items shared',       'value' => '5,180'],
    ['label' => 'Households reached', 'value' => '1,940'],
    ['label' => 'Aid grants enabled', 'value' => '22'],
    ['label' => 'Est. savings',       'value' => 'Rs 4.1M'],
];

$contributions ??= [
    ['sponsor' => 'Northwind Co · INV-0312', 'amount' => 'LKR 10,000 · 70% Sponsor / 30% Aid'],
    ['sponsor' => 'ACM Corp · INV-0306',     'amount' => 'LKR 7,500 · 50% Sponsor / 50% Aid'],
    ['sponsor' => 'MNM · INV-0298',          'amount' => 'LKR 6,500 · 100% Aid'],
];

$footnote ??= 'All figures reconcile with the append-only ledger and the nightly six-pool invariant check. 100% of contributions reached the community: 1 rupee = 1 point, no deductions.';

$pageTitle = $report['quarter'] . ' quarterly report';
$navActive = 'csr-reports';

include __DIR__ . '/../../../partials/header-sponsor-liaison.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="/sponsor-liaison/csr-reports">CSR Reports</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($report['quarter']) ?> quarterly report</span>
</nav>

<div class="form-card" style="width: 100%; max-width: 920px;">
    <div style="display: flex; align-items: center; width: 100%;">
        <div>
            <h1 class="list-row__title" style="font-size: var(--text-section);">Mithra – CSR Impact Report</h1>
            <p class="list-row__meta"><?= e($report['quarter']) ?>  ·  Prepared by <?= e($report['prepared_by']) ?>  ·  <?= e($report['prepared_at']) ?></p>
        </div>
        <div style="flex: 1 0 0;"></div>
        <img src="/img/logo-deep-slate.svg" alt="" style="height: 26px;">
        <span class="nav__wordmark" style="margin-left: var(--space-2);">Mithra</span>
    </div>

    <hr style="width: 100%; border: none; border-top: 1px solid var(--color-border); margin: 0;">

    <div style="display: flex; gap: var(--space-4); width: 100%;">
        <?php foreach ($stats as $stat): ?>
            <div style="flex: 1 0 0; background-color: var(--color-bg); border-radius: var(--radius-md); padding: 14px var(--space-4);">
                <p class="stat-card__note"><?= e($stat['label']) ?></p>
                <p class="list-row__title" style="font-size: var(--text-section);"><?= e($stat['value']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="list-row__title">Contributions this quarter</p>

    <?php foreach ($contributions as $contribution): ?>
        <div style="display: flex; align-items: center; width: 100%;">
            <span style="font-size: var(--text-ui-body); color: var(--color-text);"><?= e($contribution['sponsor']) ?></span>
            <div style="flex: 1 0 0;"></div>
            <span class="list-row__meta"><?= e($contribution['amount']) ?></span>
        </div>
    <?php endforeach; ?>

    <p class="stat-card__note"><?= e($footnote) ?></p>
</div>

<div class="actions">
    <a class="btn btn--ghost" href="/sponsor-liaison/csr-reports">Back to CSR impact</a>
    <button class="btn btn--primary" type="button" id="print-report">Print / Save as PDF</button>
</div>

<script>
document.getElementById('print-report').addEventListener('click', function () {
    window.print();
});
</script>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
