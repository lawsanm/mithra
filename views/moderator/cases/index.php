<?php

declare(strict_types=1);

/**
 * Damage cases queue — every case this moderator is mediating or has closed,
 * filtered by status.
 *
 * @var array  $filters       pills: label, state, active
 * @var string $filterSummary count line at the end of the filter bar
 * @var array  $cases         rows: title, meta, status, status_label, href
 */

$pageTitle = 'Damage cases';
$navActive = 'cases';

$chrome = 'moderator';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Damage cases</h1>
</header>

<div class="filter-bar">
    <ul class="filter-pills">
        <?php foreach ($filters as $filter): ?>
            <li>
                <a class="pill<?= $filter['active'] ? ' pill--active' : '' ?>"
                   href="<?= base_url() ?>/moderator/cases<?= $filter['state'] === '' ? '' : '?status=' . rawurlencode($filter['state']) ?>"
                   <?= $filter['active'] ? 'aria-current="true"' : '' ?>
                ><?= e($filter['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <span class="filter-bar__count"><?= e($filterSummary) ?></span>
</div>

<?php if ($cases === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon">
            <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-handshake"></use></svg>
        </span>
        <p class="empty-state__title">Nothing in this queue</p>
        <p class="empty-state__body">
            No case matches this filter. Try “All” to see every damage case in your division.
        </p>
        <a class="btn btn--primary" href="<?= base_url() ?>/moderator/cases">Show all cases</a>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($cases as $case): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($case['title']) ?></span>
                    <span class="list-row__meta"><?= e($case['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($case['status']) ?>"><?= e($case['status_label']) ?></span>
                <a class="btn btn--ghost" href="<?= e($case['href']) ?>">Open</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
