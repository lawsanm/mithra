<?php

declare(strict_types=1);

/**
 * Verifications queue — every home-membership application in this moderator's
 * division, filtered by status. The filter pills are links, so filtering is a
 * plain GET and the page works without JavaScript (Rules/CONVENTIONS.md §11).
 *
 * @var array $filters       pills: label, state, active
 * @var string $filterSummary count line at the end of the filter bar
 * @var array $verifications rows: name, meta, status, status_label, href
 * @var array|null $flash
 */

$filters       = $filters ?? [];
$filterSummary = $filterSummary ?? '';
$verifications = $verifications ?? [];

$pageTitle = 'Verifications';
$navActive = 'verifications';

$chrome = 'moderator';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Verifications</h1>
    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/moderator/address-changes">Address changes</a>
        <a class="btn btn--ghost" href="<?= base_url() ?>/moderator/reset-codes">Reset codes</a>
    </div>
</header>

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<div class="filter-bar">
    <ul class="filter-pills">
        <?php foreach ($filters as $filter): ?>
            <li>
                <a class="pill<?= $filter['active'] ? ' pill--active' : '' ?>"
                   href="<?= base_url() ?>/moderator/verifications<?= $filter['state'] === '' ? '' : '?status=' . rawurlencode($filter['state']) ?>"
                   <?= $filter['active'] ? 'aria-current="true"' : '' ?>
                ><?= e($filter['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <span class="filter-bar__count"><?= e($filterSummary) ?></span>
</div>

<?php if ($verifications === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon">
            <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-users"></use></svg>
        </span>
        <p class="empty-state__title">Nothing in this queue</p>
        <p class="empty-state__body">
            No verification matches this filter. Try “All” to see every application in your division.
        </p>
        <a class="btn btn--primary" href="<?= base_url() ?>/moderator/verifications">Show all verifications</a>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($verifications as $verification): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($verification['name']) ?></span>
                    <span class="list-row__meta"><?= e($verification['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($verification['status']) ?>"><?= e($verification['status_label']) ?></span>
                <a class="btn btn--ghost" href="<?= e($verification['href']) ?>">Review</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
