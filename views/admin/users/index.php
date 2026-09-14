<?php

declare(strict_types=1);

/**
 * User management — admin view of all platform members with search and filters.
 *
 * @var array  $stats   stat cards: total, active, frozen, new this month
 * @var array  $filters filter pills
 * @var array  $users   user rows: initials, name, division, role, balance, status, status_label, href
 * @var string $search  current search term
 */

$stats ??= [
    ['label' => 'Total users',     'value' => '2,412'],
    ['label' => 'Active',          'value' => '2,318'],
    ['label' => 'Frozen',          'value' => '14'],
    ['label' => 'New this month',  'value' => '47'],
];

$filters ??= [
    ['label' => 'All',          'slug' => '',        'active' => true],
    ['label' => 'Active',       'slug' => 'active'],
    ['label' => 'Frozen',       'slug' => 'frozen'],
    ['label' => 'Negative bal', 'slug' => 'negative'],
];

$users ??= [
    ['initials' => 'ML', 'name' => 'M. Lawsan',           'division' => 'Kollupitiya', 'role' => 'Member',    'balance' => '120 pts',  'status' => 'success', 'status_label' => 'Active',  'href' => base_url() . '/admin/users/1'],
    ['initials' => 'AA', 'name' => 'A. Akalvily',         'division' => 'Dehiwala',    'role' => 'Member',    'balance' => '−45 pts',  'status' => 'error',   'status_label' => 'Frozen',  'href' => base_url() . '/admin/users/2'],
    ['initials' => 'JK', 'name' => 'J. Kavipriya',        'division' => 'Wellawatte',  'role' => 'Moderator', 'balance' => '340 pts',  'status' => 'success', 'status_label' => 'Active',  'href' => base_url() . '/admin/users/3'],
    ['initials' => 'TM', 'name' => 'T.H.K. Madushan',     'division' => 'Bambalapitiya','role' => 'Member',   'balance' => '85 pts',   'status' => 'success', 'status_label' => 'Active',  'href' => base_url() . '/admin/users/4'],
    ['initials' => 'NK', 'name' => 'N. Kumari',           'division' => 'Kollupitiya', 'role' => 'Member',    'balance' => '−20 pts',  'status' => 'warning', 'status_label' => 'Negative', 'href' => base_url() . '/admin/users/5'],
];

$search ??= '';

$pageTitle = 'Users';
$navActive = 'users';

include __DIR__ . '/../../../partials/header-admin.php';

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

<div class="field-row">
    <div class="field">
        <input class="input" type="search" name="q" placeholder="Search name, division, role…" value="<?= e($search) ?>">
    </div>
</div>

<ul class="filter-pills">
    <?php foreach ($filters as $filter): ?>
        <li>
            <a
                class="pill<?= !empty($filter['active']) ? ' pill--active' : '' ?>"
                href="<?= base_url() ?>/admin/users?status=<?= e(rawurlencode($filter['slug'])) ?>"
                <?= !empty($filter['active']) ? 'aria-current="true"' : '' ?>
            ><?= e($filter['label']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

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
