<?php

declare(strict_types=1);

/**
 * Disaster Mode contributions — every sponsor contribution the Liaison has
 * recorded, with where each one stands in verification (Plan §14.3).
 *
 * Status flow: Awaiting Moderator → Ready to verify → Verified,
 * or Queried / Rejected when the two accounts do not agree.
 *
 * @var array  $stats         figures for the stat row
 * @var array  $filters       pills: label, state, active
 * @var string $filterSummary count text beside the pills
 * @var array  $contributions rows: id, reference, sponsor, disaster, kind, description, value,
 *                            sponsor_proof, moderator_check, status, status_label
 */

$pageTitle = 'Disaster contributions';
$navActive = 'disasters';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../../partials/header.php';

$baseUrl = base_url() . '/sponsor-liaison/disasters/contributions';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/disasters">Disasters</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Contributions</span>
</nav>

<header class="page-header">
    <h1 class="page-header__title">Disaster contributions</h1>
    <a class="btn btn--primary page-header__action" href="<?= e($baseUrl) ?>/create">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
        Record contribution
    </a>
</header>

<p class="page-intro__meta">
    Sponsors give relief to the division's Moderator off-platform. Record each contribution with the
    sponsor's proof, wait for the Moderator to confirm what they received, then verify it for the CSR report.
</p>

<div class="stat-grid stat-grid--3">
    <?php foreach ($stats as $stat): ?>
        <?php $statTone = ($stat['primary'] ?? true) ? 'primary' : ''; include __DIR__ . '/../../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<div class="filter-bar">
    <ul class="filter-pills">
        <?php foreach ($filters as $filter): ?>
            <li>
                <a class="pill<?= $filter['active'] ? ' pill--active' : '' ?>"
                   href="<?= e($baseUrl) ?><?= $filter['state'] === '' ? '' : '?status=' . rawurlencode($filter['state']) ?>"
                   <?= $filter['active'] ? 'aria-current="true"' : '' ?>
                ><?= e($filter['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <span class="filter-bar__count"><?= e($filterSummary) ?></span>
</div>

<?php if ($contributions === []): ?>
    <div class="empty-state">
        <p class="empty-state__title">No contributions recorded</p>
        <p class="empty-state__body">When a sponsor tells you they helped during Disaster Mode, record it here with their proof.</p>
    </div>
<?php else: ?>
    <div class="scroll-x">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Sponsor</th>
                    <th>Contribution</th>
                    <th style="text-align: right">Value</th>
                    <th>Sponsor's proof</th>
                    <th>Moderator's check</th>
                    <th>Status</th>
                    <th><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contributions as $row): ?>
                    <tr>
                        <td style="white-space: nowrap"><?= e($row['reference']) ?></td>
                        <td>
                            <?= e($row['sponsor']) ?><br>
                            <span class="list-row__meta"><?= e($row['disaster']) ?></span>
                        </td>
                        <td><?= e($row['kind']) ?> · <?= e($row['description']) ?></td>
                        <td style="text-align: right; white-space: nowrap"><?= e($row['value']) ?></td>
                        <td><?= e($row['sponsor_proof']) ?></td>
                        <td><?= e($row['moderator_check']) ?></td>
                        <td><span class="badge badge--<?= e($row['status']) ?>"><?= e($row['status_label']) ?></span></td>
                        <td><a class="link" href="<?= e($baseUrl . '/' . $row['id']) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="notice notice--info notice--full">
    Only verified contributions appear in sponsor CSR reports and the transparency records.
    No points move — Disaster Mode sits outside the points economy.
</div>

<?php include __DIR__ . '/../../../../partials/footer.php'; ?>
