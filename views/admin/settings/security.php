<?php

declare(strict_types=1);

/**
 * Admin settings — Security tab.
 *
 * @var bool   $twoFactorEnabled  whether 2FA is on
 * @var array  $sessions          active sessions: device, ip, last_active, current
 */

$twoFactorEnabled ??= true;

$sessions ??= [
    ['device' => 'Chrome on Windows',  'ip' => '192.168.1.42',  'last_active' => 'Now',          'current' => true],
    ['device' => 'Safari on iPhone',   'ip' => '192.168.1.108', 'last_active' => '2 hours ago',  'current' => false],
];

$pageTitle = 'Settings — Security';
$navActive = 'settings';
$settingsTab = 'security';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Settings</h1>
</header>

<ul class="filter-pills">
    <li><a class="pill" href="/admin/settings/profile">Profile</a></li>
    <li><a class="pill pill--active" href="/admin/settings/security" aria-current="true">Security</a></li>
    <li><a class="pill" href="/admin/settings/notifications">Notifications</a></li>
</ul>

<form class="form-card" method="post" action="/admin/settings/security/password">
    <?= csrf_field() ?>
    <h2 class="form-card__title">Change password</h2>

    <div class="field">
        <label class="label" for="current_password">Current password</label>
        <input class="input" type="password" id="current_password" name="current_password" required>
    </div>

    <div class="field-row">
        <div class="field">
            <label class="label" for="new_password">New password</label>
            <input class="input" type="password" id="new_password" name="new_password" required>
        </div>
        <div class="field">
            <label class="label" for="confirm_password">Confirm new password</label>
            <input class="input" type="password" id="confirm_password" name="confirm_password" required>
        </div>
    </div>

    <div class="form-card__actions">
        <button class="btn btn--primary" type="submit">Update password</button>
    </div>
</form>

<div class="form-card">
    <h2 class="form-card__title">Two-factor authentication</h2>
    <div class="toggle-row">
        <div>
            <strong>2FA is <?= $twoFactorEnabled ? 'enabled' : 'disabled' ?></strong>
            <p class="text-muted">Adds an extra layer of security to your account</p>
        </div>
        <form method="post" action="/admin/settings/security/2fa">
            <?= csrf_field() ?>
            <input type="hidden" name="enabled" value="<?= $twoFactorEnabled ? '0' : '1' ?>">
            <button class="btn btn--ghost" type="submit"><?= $twoFactorEnabled ? 'Disable' : 'Enable' ?></button>
        </form>
    </div>
</div>

<section class="section">
    <h2 class="section__title">Active sessions</h2>
    <ul class="row-list">
        <?php foreach ($sessions as $session): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($session['device']) ?><?= $session['current'] ? ' (this device)' : '' ?></span>
                    <span class="list-row__meta"><?= e($session['ip']) ?> · <?= e($session['last_active']) ?></span>
                </div>
                <?php if (!$session['current']): ?>
                    <form method="post" action="/admin/settings/security/revoke-session">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ip" value="<?= e($session['ip']) ?>">
                        <button class="btn btn--danger" type="submit">Revoke</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
