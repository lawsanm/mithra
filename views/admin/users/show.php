<?php

declare(strict_types=1);

/**
 * User detail — admin view of an individual member.
 *
 * @var array $user       initials, name, division, role, status, status_label, email, phone, joined_at, trust_score, balance
 * @var array $activity   recent activity items: icon_type, title, meta
 * @var array $stats      label, value
 */

$user ??= [
    'id'           => 1,
    'initials'     => 'ML',
    'name'         => 'M. Lawsan',
    'division'     => 'Kollupitiya',
    'role'         => 'Member',
    'status'       => 'success',
    'status_label' => 'Active',
    'email'        => 'lawsan@example.com',
    'phone'        => '+94 77 234 5678',
    'joined_at'    => '15 Nov 2025',
    'trust_score'  => 85,
    'balance'      => '120 pts',
];

$stats ??= [
    ['label' => 'Items listed',   'value' => '4'],
    ['label' => 'Transactions',   'value' => '18'],
    ['label' => 'Disputes',       'value' => '0'],
];

$activity ??= [
    ['icon_type' => 'lend',   'title' => 'Borrowed Pressure Washer',    'meta' => '2 days ago'],
    ['icon_type' => 'return', 'title' => 'Returned Rice Cooker',         'meta' => '5 days ago'],
    ['icon_type' => 'lend',   'title' => 'Listed Camping Tent',          'meta' => '1 week ago'],
];

$pageTitle = e($user['name']) . ' — User';
$navActive = 'users';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/admin/users">Users</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current"><?= e($user['name']) ?></span>
</nav>

<header class="page-header">
    <h1 class="page-header__title"><?= e($user['name']) ?></h1>
    <span class="badge badge--<?= e($user['status']) ?>"><?= e($user['status_label']) ?></span>
</header>

<div class="users-layout">
    <div>
        <div class="form-card" style="width: 100%; max-width: 100%;">
            <h2 class="form-card__legend">Member details</h2>

            <div class="line-item">
                <span class="line-item__label">Division</span>
                <span class="line-item__value"><?= e($user['division']) ?></span>
            </div>
            <div class="line-item">
                <span class="line-item__label">Role</span>
                <span class="line-item__value"><?= e($user['role']) ?></span>
            </div>
            <div class="line-item">
                <span class="line-item__label">Email</span>
                <span class="line-item__value"><?= e($user['email']) ?></span>
            </div>
            <div class="line-item">
                <span class="line-item__label">Phone</span>
                <span class="line-item__value"><?= e($user['phone']) ?></span>
            </div>
            <div class="line-item">
                <span class="line-item__label">Joined</span>
                <span class="line-item__value"><?= e($user['joined_at']) ?></span>
            </div>
            <div class="line-item">
                <span class="line-item__label">Trust score</span>
                <span class="line-item__value"><strong style="color: var(--color-primary);"><?= e((string) $user['trust_score']) ?></strong> / 100</span>
            </div>
            <div class="line-item">
                <span class="line-item__label">Points balance</span>
                <span class="line-item__value"><?= e($user['balance']) ?></span>
            </div>
        </div>

        <section class="section" style="margin-top: var(--space-6);">
            <h2 class="section__title">Recent activity</h2>
            <div class="activity-list">
                <?php foreach ($activity as $item): ?>
                    <div class="activity-item">
                        <span class="activity-item__icon activity-item__icon--<?= e($item['icon_type']) ?>">
                            <?= $item['icon_type'] === 'lend' ? '→' : ($item['icon_type'] === 'return' ? '←' : '!') ?>
                        </span>
                        <div class="activity-item__body">
                            <span class="activity-item__title"><?= e($item['title']) ?></span>
                            <span class="activity-item__meta"><?= e($item['meta']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="user-panel">
        <div class="user-panel__header">
            <span class="avatar" style="width:48px;height:48px;font-size:var(--text-lede);"><?= e($user['initials']) ?></span>
            <div>
                <strong><?= e($user['name']) ?></strong>
                <span class="list-row__meta"><?= e($user['division']) ?> · <?= e($user['role']) ?></span>
            </div>
        </div>

        <div class="user-panel__stats">
            <?php foreach ($stats as $stat): ?>
                <div class="user-panel__stat">
                    <strong><?= e($stat['value']) ?></strong>
                    <span><?= e($stat['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-top: var(--space-4);">
            <a class="btn btn--ghost" href="<?= base_url() ?>/admin/users" style="width:100%; text-align:center;">Back to users</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
