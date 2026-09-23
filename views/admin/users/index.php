<?php

declare(strict_types=1);

/**
 * User management — admin view of all platform members with search and filters.
 *
 * @var array  $stats   stat cards: total, active, suspended, new this month
 * @var array  $filters status pills: label, slug, active(bool)
 * @var array  $users   user rows: initials, name, division, role, balance, status, status_label, href
 * @var string $status  the active status filter ('' for all)
 * @var string $search  current search term
 */

$pageTitle = 'Users';
$navActive = 'users';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Users</h1>
    <button class="btn btn--ghost page-header__action" disabled title="Export coming soon">Export CSV</button>
</header>

<div class="stat-grid stat-grid--4">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-card">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value stat-card__value--primary"><?= e($stat['value']) ?></strong>
        </div>
    <?php endforeach; ?>
</div>

<form class="field-row" method="get" action="<?= base_url() ?>/admin/users" role="search">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="field">
        <input class="input" type="search" name="q" placeholder="Search name or division" aria-label="Search name or division" value="<?= e($search) ?>">
    </div>
    <button class="btn btn--ghost" type="submit">Search</button>
</form>

<ul class="filter-pills">
    <?php foreach ($filters as $pill): ?>
        <li>
            <a
                class="pill<?= $pill['active'] ? ' pill--active' : '' ?>"
                href="<?= e(base_url() . '/admin/users?' . http_build_query(array_filter(['status' => $pill['slug'], 'q' => $search]))) ?>"
                <?= $pill['active'] ? 'aria-current="true"' : '' ?>
            ><?= e($pill['label']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($users === []): ?>
    <p class="empty-state__body">No accounts match.</p>
<?php endif; ?>

<ul class="row-list">
    <?php foreach ($users as $user): ?>
        <li class="list-row">
            <span class="avatar"><?= e($user['initials']) ?></span>
            <div class="list-row__body">
                <span class="list-row__title"><?= e($user['name']) ?></span>
                <span class="list-row__meta"><?= e($user['division']) ?> · <?= e($user['role']) ?> · <?= e($user['balance']) ?></span>
            </div>
            <span class="badge badge--<?= e($user['status']) ?>"><?= e($user['status_label']) ?></span>
            <a class="btn btn--ghost" href="<?= e($user['href']) ?>">View</a>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
