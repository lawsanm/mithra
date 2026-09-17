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
    <li><a class="pill" href="<?= base_url() ?>/admin/settings/profile">Profile</a></li>
    <li><a class="pill pill--active" href="<?= base_url() ?>/admin/settings/security" aria-current="true">Security</a></li>
    <li><a class="pill" href="<?= base_url() ?>/admin/settings/notifications">Notifications</a></li>
</ul>

<div class="form-card" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
    
    <h2 class="form-card__title">Change password</h2>

    <div class="field">
        <label class="label" for="current_password">Current password</label>
        <input class="input" type="password" id="current_password" name="current_password" required disabled>
    </div>

    <div class="field-row">
        <div class="field">
            <label class="label" for="new_password">New password</label>
            <input class="input" type="password" id="new_password" name="new_password" required disabled>
        </div>
        <div class="field">
            <label class="label" for="confirm_password">Confirm new password</label>
            <input class="input" type="password" id="confirm_password" name="confirm_password" required disabled>
        </div>
    </div>

    <div class="form-card__actions">
        <button class="btn btn--primary" type="submit" disabled>Update password</button>
    </div>
</div>

<div class="form-card">
    <h2 class="form-card__title">Two-factor authentication</h2>
    <div class="toggle-row">
        <div>
            <strong>2FA is <?= $twoFactorEnabled ? 'enabled' : 'disabled' ?></strong>
            <p class="text-muted">Adds an extra layer of security to your account</p>
        </div>
        <div data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>

            <button class="btn btn--ghost" type="submit" disabled><?= $twoFactorEnabled ? 'Disable' : 'Enable' ?></button>
        </div>
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
                    <div data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>

                        <button class="btn btn--danger" type="submit" disabled>Revoke</button>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
