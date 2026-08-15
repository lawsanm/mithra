<?php

declare(strict_types=1);

/**
 * Admin settings — Profile tab.
 *
 * @var array $admin  admin user: name, email, phone, division, role, joined
 */

$admin ??= [
    'name'     => 'Hasith Kaveesha',
    'email'    => 'admin@mithra.lk',
    'phone'    => '+94 77 123 4567',
    'division' => 'All divisions',
    'role'     => 'Admin',
    'joined'   => '15 Jan 2026',
];

$pageTitle = 'Settings — Profile';
$navActive = 'settings';
$settingsTab = 'profile';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Settings</h1>
</header>

<ul class="filter-pills">
    <li><a class="pill pill--active" href="<?= base_url() ?>/admin/settings/profile" aria-current="true">Profile</a></li>
    <li><a class="pill" href="<?= base_url() ?>/admin/settings/security">Security</a></li>
    <li><a class="pill" href="<?= base_url() ?>/admin/settings/notifications">Notifications</a></li>
</ul>

<form class="form-card" method="post" action="<?= base_url() ?>/admin/settings/profile">
    <?= csrf_field() ?>

    <div class="field">
        <label class="label" for="name">Full name</label>
        <input class="input" type="text" id="name" name="name" value="<?= e($admin['name']) ?>">
    </div>

    <div class="field-row">
        <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" type="email" id="email" name="email" value="<?= e($admin['email']) ?>">
        </div>
        <div class="field">
            <label class="label" for="phone">Phone</label>
            <input class="input" type="tel" id="phone" name="phone" value="<?= e($admin['phone']) ?>">
        </div>
    </div>

    <div class="field-row">
        <div class="field">
            <label class="label" for="division">Division</label>
            <input class="input" type="text" id="division" name="division" value="<?= e($admin['division']) ?>" disabled>
        </div>
        <div class="field">
            <label class="label" for="role">Role</label>
            <input class="input" type="text" id="role" name="role" value="<?= e($admin['role']) ?>" disabled>
        </div>
    </div>

    <div class="field">
        <label class="label">Member since</label>
        <p class="field__static"><?= e($admin['joined']) ?></p>
    </div>

    <div class="form-card__actions">
        <button class="btn btn--primary" type="submit">Save changes</button>
    </div>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
