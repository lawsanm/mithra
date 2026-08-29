<?php

declare(strict_types=1);

/**
 * Admin notifications — platform-wide notification log.
 *
 * @var array $filters  filter pills
 * @var array $notices  notification rows: icon, title, meta, time, read
 */

$filters ??= [
    ['label' => 'All',       'slug' => '',        'active' => true],
    ['label' => 'Disputes',  'slug' => 'disputes'],
    ['label' => 'Pools',     'slug' => 'pools'],
    ['label' => 'Users',     'slug' => 'users'],
    ['label' => 'System',    'slug' => 'system'],
];

$notices ??= [
    ['icon' => '⚠', 'title' => 'New dispute filed — booking #B-2188',                   'meta' => 'M. Lawsan vs T.H.K. Madushan · awaiting moderator',    'time' => '10 min ago',  'read' => false],
    ['icon' => '✓', 'title' => 'Invariant check passed',                                 'meta' => 'All six pools reconciled · 192,970 pts',                'time' => '7 hours ago', 'read' => false],
    ['icon' => '⚡', 'title' => 'Sponsor injection received — INV-0312',                  'meta' => 'Northwind Co · +10,000 pts',                            'time' => 'Yesterday',   'read' => true],
    ['icon' => '🔒', 'title' => 'Account frozen — A. Akalvily',                           'meta' => 'Negative balance exceeded −100 pts floor',              'time' => '2 days ago',  'read' => true],
    ['icon' => '👤', 'title' => 'New moderator appointed — J. Kavipriya',                  'meta' => 'Wellawatte division · appointed by Admin',              'time' => '3 days ago',  'read' => true],
    ['icon' => '📊', 'title' => 'Monthly report generated',                                'meta' => 'June 2026 — 23 divisions, 4,120 bookings',             'time' => '1 week ago',  'read' => true],
];

$pageTitle = 'Notifications';
$navActive = 'notifications';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Notifications</h1>
    <form method="post" action="<?= base_url() ?>/admin/notifications/mark-all-read" class="page-header__action">
        <?= csrf_field() ?>
        <button class="btn btn--ghost" type="submit">Mark all read</button>
    </form>
</header>

<ul class="filter-pills">
    <?php foreach ($filters as $filter): ?>
        <li>
            <a
                class="pill<?= !empty($filter['active']) ? ' pill--active' : '' ?>"
                href="<?= base_url() ?>/admin/notifications?type=<?= e(rawurlencode($filter['slug'])) ?>"
                <?= !empty($filter['active']) ? 'aria-current="true"' : '' ?>
            ><?= e($filter['label']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<ul class="row-list">
    <?php foreach ($notices as $notice): ?>
        <li class="list-row<?= !$notice['read'] ? ' list-row--unread' : '' ?>">
            <span class="list-row__icon" aria-hidden="true"><?= e($notice['icon']) ?></span>
            <div class="list-row__body">
                <span class="list-row__title"><?= e($notice['title']) ?></span>
                <span class="list-row__meta"><?= e($notice['meta']) ?></span>
            </div>
            <span class="list-row__time"><?= e($notice['time']) ?></span>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
