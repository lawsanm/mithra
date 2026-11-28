<?php

declare(strict_types=1);

/**
 * User detail — admin view of an individual member.
 *
 * @var array $user       id, initials, name, division, role, status, status_label, email, phone, joined_at,
 *                        trust_score, balance, is_resident, can_suspend, can_reinstate
 * @var array $activity   the account's latest point movements: icon_type, title, meta
 * @var array $stats      label, value
 * @var array $suspension_history prior suspension rows, newest first
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
            <?php if ($user['is_resident']): ?>
                <div class="line-item">
                    <span class="line-item__label">Trust score</span>
                    <span class="line-item__value"><strong style="color: var(--color-primary);"><?= e((string) $user['trust_score']) ?></strong> / 100</span>
                </div>
            <?php endif; ?>
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

        <section class="section u-mt-6">
            <h2 class="section__title">Suspension history</h2>
            <?php if ($suspension_history === []): ?>
                <p class="empty-state__body">No suspension history.</p>
            <?php else: ?>
                <ul class="row-list">
                    <?php foreach ($suspension_history as $record): ?>
                        <li class="list-row">
                            <div class="list-row__body">
                                <span class="list-row__title"><?= e($record['reason']) ?></span>
                                <span class="list-row__meta">
                                    Started <?= e($record['started_at']) ?> by <?= e($record['admin_name']) ?>
                                    · <?= $record['ended_at'] === null ? 'Current suspension' : 'Ended ' . e($record['ended_at']) ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <div class="user-panel">
        <div class="user-panel__header">
            <span class="avatar" style="width:48px;height:48px;font-size:var(--text-lede);"><?= e($user['initials']) ?></span>
            <div class="list-row__body">
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
            <?php if ($user['can_suspend']): ?>
                <form class="stack" method="post" action="<?= base_url() ?>/admin/users/<?= e((string) $user['id']) ?>/suspension" data-confirm="Suspend this member account? The reason will be recorded in the account history." novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="suspend">
                    <div class="field">
                        <label class="field__label" for="suspension-reason">Suspension reason</label>
                        <textarea class="input" id="suspension-reason" name="reason" rows="3"></textarea>
                    </div>
                    <button class="btn btn--danger" type="submit" style="width:100%;">Suspend member</button>
                </form>
            <?php elseif ($user['can_reinstate']): ?>
                <form method="post" action="<?= base_url() ?>/admin/users/<?= e((string) $user['id']) ?>/suspension" data-confirm="Reinstate this member account? The past suspension will remain in the account history." novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reinstate">
                    <button class="btn btn--primary" type="submit" style="width:100%;">Reinstate member</button>
                </form>
            <?php endif; ?>
            <a class="btn btn--ghost" href="<?= base_url() ?>/admin/users" style="width:100%; text-align:center;">Back to users</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
