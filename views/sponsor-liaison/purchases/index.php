<?php

declare(strict_types=1);

/**
 * Purchases & contributions. Figma "Purchases — Contributions" (378:76).
 *
 * @var array  $purchases rows: id, date, sponsor, receipt, allocation, amount
 * @var string $search    current search term (receipt no.)
 * @var string $sponsor   current sponsor filter
 * @var string $dateRange current date range filter
 */

// Sample view data — replaced by the controller once SponsorLiaisonController lands.
$purchases ??= [
    ['id' => 1, 'date' => '15 Jul', 'sponsor' => 'Northwind Co', 'receipt' => 'INV-0312', 'allocation' => 'allocation 70% Sponsor · 30% Aid', 'amount' => 'LKR 10,000'],
    ['id' => 2, 'date' => '05 Jul', 'sponsor' => 'ACM Corp',     'receipt' => 'INV-0306', 'allocation' => 'allocation 50% Sponsor · 50% Aid', 'amount' => 'LKR 7,500'],
    ['id' => 3, 'date' => '28 Jun', 'sponsor' => 'MNM',          'receipt' => 'INV-0298', 'allocation' => 'allocation 100% Aid',              'amount' => 'LKR 6,500'],
    ['id' => 4, 'date' => '19 Jun', 'sponsor' => 'Global Ltd',   'receipt' => 'INV-0265', 'allocation' => 'allocation 70% Sponsor · 30% Aid', 'amount' => 'LKR 5,000'],
    ['id' => 5, 'date' => '10 Jun', 'sponsor' => 'Texa',         'receipt' => 'INV-0276', 'allocation' => 'allocation 60% Sponsor · 40% Aid', 'amount' => 'LKR 5,000'],
];

$search    ??= '';
$sponsor   ??= '';
$dateRange ??= '';

$pageTitle = 'Purchases & contributions';
$navActive = 'purchases';

include __DIR__ . '/../../../partials/header-sponsor-liaison.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Purchases &amp; contributions</h1>
    <a class="btn btn--primary page-header__action" href="/sponsor-liaison/purchases/create">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
        Record contribution
    </a>
</header>

<form class="field-row" method="get" action="/sponsor-liaison/purchases">
    <div class="field">
        <input class="input input--search" type="search" name="q" placeholder="Search by receipt no." value="<?= e($search) ?>">
    </div>
    <div class="field">
        <select class="input" name="sponsor" data-auto-submit>
            <option value="">All sponsors</option>
            <?php foreach (['Northwind Co', 'ACM Corp', 'MNM', 'Global Ltd', 'Texa'] as $option): ?>
                <option value="<?= e($option) ?>"<?= $sponsor === $option ? ' selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <input class="input input--date" type="text" name="date_range" placeholder="Date range" value="<?= e($dateRange) ?>">
    </div>
</form>

<?php if ($purchases === []): ?>
    <div class="empty-state">
        <p class="empty-state__title">No contributions recorded yet</p>
        <p class="empty-state__body">Record a sponsor's contribution to see it here.</p>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($purchases as $purchase): ?>
            <li class="list-row">
                <span style="width: 70px; flex-shrink: 0; color: var(--color-text-muted); font-size: var(--text-ui-body);"><?= e($purchase['date']) ?></span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($purchase['sponsor']) ?></span>
                    <span class="list-row__meta">Receipt <?= e($purchase['receipt']) ?>  ·  <?= e($purchase['allocation']) ?></span>
                </div>
                <strong class="list-row__title" style="color: var(--color-success-text);"><?= e($purchase['amount']) ?></strong>
                <a class="btn btn--ghost" href="/sponsor-liaison/purchases/<?= e((string) $purchase['id']) ?>">View</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="notice notice--info notice--full">
    Each recorded contribution needs the receipt number and the Sponsor Pool / Aid Pool allocation split. Splits are visible to everyone on the Transparency Dashboard.
</div>

<?php $pageScripts = ['filter-select.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
