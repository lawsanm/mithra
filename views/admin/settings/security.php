<?php

declare(strict_types=1);

/**
 * Admin settings — Security tab: change the Admin's own password
 * (Plan §20.1 module 1.1).
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

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

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

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
