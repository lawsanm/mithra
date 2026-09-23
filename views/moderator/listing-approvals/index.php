<?php

declare(strict_types=1);

/**
 * Listing approvals queue — listings waiting on a reviewer's declared-value
 * decision before they go live (Plan §9.2), filtered by status. The same view
 * serves the moderator and, for moderators' own listings, the Admin.
 *
 * @var string $chrome        'moderator' or 'admin' — which page chrome to use
 * @var string $basePath      URL of this queue
 * @var array  $filters       pills: label, href, active
 * @var string $filterSummary count line at the end of the filter bar
 * @var array  $listings      rows: title, meta, status, status_label, href
 * @var array|null $flash
 */

$chrome   = ($chrome ?? 'moderator') === 'admin' ? 'admin' : 'moderator';
$filters  = $filters ?? [];
$listings = $listings ?? [];

$pageTitle = 'Listing approvals';
$navActive = 'listing-approvals';

include __DIR__ . '/../../../partials/header-' . $chrome . '.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Listing approvals</h1>
</header>

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<div class="filter-bar">
    <ul class="filter-pills">
        <?php foreach ($filters as $filter): ?>
            <li>
                <a class="pill<?= $filter['active'] ? ' pill--active' : '' ?>"
                   href="<?= e($filter['href']) ?>"
                   <?= $filter['active'] ? 'aria-current="true"' : '' ?>
                ><?= e($filter['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <span class="filter-bar__count"><?= e($filterSummary ?? '') ?></span>
</div>

<?php if ($listings === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon">
            <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-package"></use></svg>
        </span>
        <p class="empty-state__title">Nothing in this queue</p>
        <p class="empty-state__body">
            No listing you review matches this filter. Try “All” to see every listing you have decided.
        </p>
        <a class="btn btn--primary" href="<?= e($basePath ?? '') ?>">Show all listings</a>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($listings as $listing): ?>
            <li class="list-row">
                <span class="thumb thumb--sm">Photo</span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($listing['title']) ?></span>
                    <span class="list-row__meta"><?= e($listing['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($listing['status']) ?>"><?= e($listing['status_label']) ?></span>
                <a class="btn btn--ghost" href="<?= e($listing['href']) ?>">Review</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
