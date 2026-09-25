<?php

declare(strict_types=1);

/**
 * User detail — admin view of an individual member.
 *
 * @var array $user       initials, name, division, role, status, status_label, email, phone, joined_at, trust_score, balance
 * @var array $activity   the account's latest point movements: icon_type, title, meta
 * @var array $stats      label, value
 */

$pageTitle = $user['name'] . ' — User';
$navActive = 'users';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

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
        <div class="form-card form-card--wide">
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

        <section class="section u-mt-6">
            <h2 class="section__title">Recent point movements</h2>
            <?php if ($activity === []): ?>
                <p class="empty-state__body">No point movements yet.</p>
            <?php endif; ?>
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
