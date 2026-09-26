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

$pageTitle = 'Purchases & contributions';
$navActive = 'purchases';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Purchases &amp; contributions</h1>
    <a class="btn btn--primary page-header__action" href="<?= base_url() ?>/sponsor-liaison/purchases/create">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
        Record contribution
    </a>
</header>

<form class="field-row" method="get" action="<?= base_url() ?>/sponsor-liaison/purchases" novalidate>
    <div class="field">
        <input class="input input--search" aria-label="Search purchases" type="search" name="q" placeholder="Search by receipt no." value="<?= e($search) ?>">
    </div>
    <div class="field">
        <select class="input" aria-label="Sponsor" name="sponsor" data-auto-submit>
            <option value="">All sponsors</option>
            <?php foreach ($sponsorOptions as $option): ?>
                <option value="<?= e($option) ?>"<?= $sponsor === $option ? ' selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <input class="input input--date" type="text" name="date_range" aria-label="Date or month" placeholder="Date or month (e.g. Jul)" value="<?= e($dateRange) ?>">
    </div>
    <button class="btn btn--ghost" type="submit">Apply filters</button>
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
                <strong class="list-row__title u-text-success"><?= e($purchase['amount']) ?></strong>
                <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/purchases/<?= e((string) $purchase['id']) ?>">View</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="notice notice--info notice--full">
    Each recorded contribution needs the receipt number and the Sponsor Pool / Aid Pool allocation split. Splits are visible to everyone on the Transparency Dashboard.
</div>

<?php $pageScripts = ['filter-select.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
