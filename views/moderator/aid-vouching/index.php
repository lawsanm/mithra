<?php

declare(strict_types=1);

/**
 * Aid vouching queue — disaster relief aid requests waiting on this moderator's
 * vouch. Vouching is a state change, so each row posts its own form; the
 * service behind it belongs to the aid grant module.
 *
 * @var array  $filters       pills: label, state, active
 * @var string $filterSummary count line at the end of the filter bar
 * @var array  $requests      rows: id, title, meta, status, status_label, vouchable
 */

$pageTitle = 'Aid vouching';
$navActive = 'disasters';

$chrome = 'moderator';
include __DIR__ . '/../../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link link" href="<?= base_url() ?>/moderator/disasters">Disasters</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Aid vouching</span>
</nav>

<header class="page-header">
    <h1 class="page-header__title">Aid vouching</h1>
</header>

<div class="filter-bar">
    <ul class="filter-pills">
        <?php foreach ($filters as $filter): ?>
            <li>
                <a class="pill<?= $filter['active'] ? ' pill--active' : '' ?>"
                   href="<?= base_url() ?>/moderator/aid-vouching<?= $filter['state'] === '' ? '' : '?status=' . rawurlencode($filter['state']) ?>"
                   <?= $filter['active'] ? 'aria-current="true"' : '' ?>
                ><?= e($filter['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <span class="filter-bar__count"><?= e($filterSummary) ?></span>
</div>

<?php if ($requests === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon">
            <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-heart"></use></svg>
        </span>
        <p class="empty-state__title">Nothing in this queue</p>
        <p class="empty-state__body">
            No aid request matches this filter. Try “All” to see every request in your division.
        </p>
        <a class="btn btn--primary" href="<?= base_url() ?>/moderator/aid-vouching">Show all requests</a>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($requests as $request): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($request['title']) ?></span>
                    <span class="list-row__meta"><?= e($request['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($request['status']) ?>"><?= e($request['status_label']) ?></span>
                <a class="btn btn--ghost" href="<?= base_url() ?>/aid-grants/<?= rawurlencode($request['id']) ?>">View request</a>
                <?php if ($request['vouchable']): ?>
                    <div data-demo-form>
                        <p class="demo-note">Preview only. Saving is not available yet.</p>
                        <button class="btn btn--primary" type="submit" disabled>Vouch</button>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
