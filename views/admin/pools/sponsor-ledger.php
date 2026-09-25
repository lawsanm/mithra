<?php

declare(strict_types=1);

/**
 * Sponsor Fund Ledger — every sponsor contribution converted to points.
 *
 * @var array $summary totalReceived, totalUsed, remaining (each: value, sub)
 * @var array $inflows rows: date, sponsor, ref, category, cash, pts, status, status_label
 */

$pageTitle = 'Sponsor Fund Ledger';
$navActive = 'pools';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <div>
        <h1 class="page-header__title">Sponsor Fund Ledger</h1>
        <p class="page-intro__meta">Cash → Points conversions · 1:1 ratio · maintenance costs only</p>
    </div>
    <div class="page-header__action actions">
        <button class="btn btn--ghost" disabled title="Export coming soon">Export CSV</button>
        <a class="btn btn--ghost" href="<?= base_url() ?>/admin/pools">Back to Pools</a>
    </div>
</header>

<div class="stat-grid stat-grid--3">
    <div class="stat-card" style="background-color: var(--color-info-tint);">
        <span class="stat-card__label stat-card__label--caps">Total Received</span>
        <strong class="stat-card__value stat-card__value--primary"><?= e($summary['totalReceived']['value']) ?></strong>
        <span class="stat-card__note"><?= e($summary['totalReceived']['sub']) ?></span>
    </div>
    <div class="stat-card" style="background-color: var(--color-accent-tint);">
        <span class="stat-card__label stat-card__label--caps">Total Used</span>
        <strong class="stat-card__value" style="color: var(--color-accent-text);"><?= e($summary['totalUsed']['value']) ?></strong>
        <span class="stat-card__note"><?= e($summary['totalUsed']['sub']) ?></span>
    </div>
    <div class="stat-card" style="background-color: var(--color-success-tint);">
        <span class="stat-card__label stat-card__label--caps">Remaining Balance</span>
        <strong class="stat-card__value u-text-success"><?= e($summary['remaining']['value']) ?></strong>
        <span class="stat-card__note"><?= e($summary['remaining']['sub']) ?></span>
    </div>
</div>

<h2 class="section__title">Contributions received</h2>

<div class="notice notice--info notice--full">
    Each contribution is recorded by the Sponsor Liaison against its receipt and converted at 1 rupee = 1 point, split between the Sponsor Pool and the Aid Pool as the sponsor chose.
</div>

<?php if ($inflows === []): ?>
    <p class="empty-state__body">No sponsor contribution has been recorded yet.</p>
<?php else: ?>
    <div class="scroll-x">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Sponsor</th>
                    <th>Ref</th>
                    <th>Split</th>
                    <th>Cash (Rs)</th>
                    <th>Pts Credited</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inflows as $row): ?>
                    <tr>
                        <td><?= e($row['date']) ?></td>
                        <td><strong><?= e($row['sponsor']) ?></strong></td>
                        <td><span style="font-family: monospace; font-size: var(--text-ui-caption);"><?= e($row['ref']) ?></span></td>
                        <td><span class="badge badge--info"><?= e($row['category']) ?></span></td>
                        <td><?= e($row['cash']) ?></td>
                        <td><strong class="u-text-success"><?= e($row['pts']) ?></strong></td>
                        <td><span class="badge badge--<?= e($row['status']) ?>"><?= e($row['status_label']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
