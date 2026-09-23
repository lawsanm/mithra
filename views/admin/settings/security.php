<?php

declare(strict_types=1);

/**
 * Admin settings — Security tab: change the Admin's own password, and issue
 * reset codes for accounts that are locked out (Plan §20.1 module 1.1).
 *
 * Changing the password signs out every other session (SessionMiddleware), so
 * no per-device session list is kept.
 *
 * @var array|null $flash
 */

$errors   = [];
$returnTo = '/admin/settings/security';

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

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<?php include __DIR__ . '/../../../partials/change-password-form.php'; ?>

<section class="panel panel--wide">
    <h2 class="panel__heading">Locked-out accounts</h2>
    <p class="record-meta">
        A member without an email asks their division moderator for a reset code. You can issue one for
        any account — including moderators, the Sponsor Liaison and sponsors — after checking their NIC.
    </p>
    <a class="btn btn--ghost" href="<?= base_url() ?>/admin/reset-codes">Issue a reset code</a>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
