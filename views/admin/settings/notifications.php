<?php

declare(strict_types=1);

/**
 * Admin settings — Notifications tab.
 *
 * @var array $prefs  notification preferences: label, description, email, push
 */

$prefs ??= [
    ['label' => 'Disputes',           'description' => 'New disputes and escalations',           'email' => true,  'push' => true],
    ['label' => 'Pool alerts',        'description' => 'Invariant failures and low-balance warnings', 'email' => true, 'push' => true],
    ['label' => 'User events',        'description' => 'Freezes, negative balances, new signups', 'email' => true,  'push' => false],
    ['label' => 'Moderator actions',   'description' => 'Appointments, removals, performance flags', 'email' => false, 'push' => true],
    ['label' => 'Sponsor injections', 'description' => 'New sponsor payments and invoice receipts', 'email' => true,  'push' => false],
    ['label' => 'System / cron',      'description' => 'Cron failures and system health',         'email' => true,  'push' => true],
];

$pageTitle = 'Settings — Notifications';
$navActive = 'settings';
$settingsTab = 'notifications';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Settings</h1>
</header>

<ul class="filter-pills">
    <li><a class="pill" href="<?= base_url() ?>/admin/settings/profile">Profile</a></li>
    <li><a class="pill" href="<?= base_url() ?>/admin/settings/security">Security</a></li>
    <li><a class="pill pill--active" href="<?= base_url() ?>/admin/settings/notifications" aria-current="true">Notifications</a></li>
</ul>

<form class="form-card" method="post" action="<?= base_url() ?>/admin/settings/notifications">
    <?= csrf_field() ?>

    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th style="text-align: center">Email</th>
                <th style="text-align: center">Push</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prefs as $i => $pref): ?>
                <tr>
                    <td>
                        <strong><?= e($pref['label']) ?></strong>
                        <span class="text-muted"><?= e($pref['description']) ?></span>
                    </td>
                    <td style="text-align: center">
                        <input type="checkbox" name="prefs[<?= e((string) $i) ?>][email]" value="1"<?= $pref['email'] ? ' checked' : '' ?>>
                    </td>
                    <td style="text-align: center">
                        <input type="checkbox" name="prefs[<?= e((string) $i) ?>][push]" value="1"<?= $pref['push'] ? ' checked' : '' ?>>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="form-card__actions">
        <button class="btn btn--primary" type="submit">Save preferences</button>
    </div>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
