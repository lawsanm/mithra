<?php

declare(strict_types=1);

/**
 * Settings. Figma: "Settings" (95:163).
 *
 * @var array $account     email, mobile, password age
 * @var array $preferences toggles: key, title, note, enabled
 * @var array $errors      per-field messages from the Validator
 */

// Sample view data — replaced by the controller once SettingsController lands.
$account ??= [
    'email'        => 'lawsan@email.com',
    'mobile'       => '+94 77 123 4567',
    'password_age' => 'Last changed 4 months ago.',
];

$preferences ??= [
    [
        'key'     => 'receive_gifts',
        'title'   => 'Receive gifts',
        'note'    => 'Allow other members to send you point gifts. Turning this off hides you '
                   . 'from the gift recipient list.',
        'enabled' => true,
    ],
    [
        'key'     => 'mfa',
        'title'   => 'Two-factor authentication (MFA)',
        'note'    => 'Require a one-time code (SMS or authenticator app) in addition to your '
                   . 'password when you log in.',
        'enabled' => true,
    ],
];

$errors ??= [];

$pageTitle = 'Settings';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="page-header__title">Settings</h1>

<div class="panel panel--wide" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
    

    <h2 class="panel__heading">Account details</h2>

    <div class="field">
        <label class="field__label" for="settings-email">Email</label>
        <input class="input" type="email" id="settings-email" name="email" value="<?= e($account['email']) ?>" required disabled>
        <?php if (isset($errors['email'])): ?>
            <span class="field__error"><?= e($errors['email']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="settings-mobile">Mobile number</label>
        <input class="input" type="tel" id="settings-mobile" name="mobile" value="<?= e($account['mobile']) ?>" required disabled>
        <?php if (isset($errors['mobile'])): ?>
            <span class="field__error"><?= e($errors['mobile']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="settings-password">Password</label>
        <input class="input" type="password" id="settings-password" name="password" autocomplete="new-password" placeholder="••••••••••" disabled>
        <span class="field__hint"><?= e($account['password_age']) ?></span>
        <?php if (isset($errors['password'])): ?>
            <span class="field__error"><?= e($errors['password']) ?></span>
        <?php endif; ?>
    </div>

    <button class="btn btn--primary" type="submit" disabled>Save changes</button>
</div>

<div class="panel panel--wide" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
    

    <h2 class="panel__heading">Preferences</h2>

    <?php foreach ($preferences as $preference): ?>
        <div class="setting-row">
            <span class="setting-row__body">
                <label class="setting-row__title" for="pref-<?= e($preference['key']) ?>">
                    <?= e($preference['title']) ?>
                </label>
                <span class="setting-row__note"><?= e($preference['note']) ?></span>
            </span>
            <input
                class="toggle"
                type="checkbox"
                id="pref-<?= e($preference['key']) ?>"
                name="preferences[<?= e($preference['key']) ?>]"
                value="1"
                <?= $preference['enabled'] ? 'checked' : '' ?>
             disabled>
        </div>
    <?php endforeach; ?>
</div>

<section class="panel panel--wide">
    <h2 class="panel__heading">About Mithra</h2>
    <a class="link panel__link" href="<?= base_url() ?>/help">Help &amp; FAQ  →</a>
    <a class="link panel__link" href="<?= base_url() ?>/transparency">Transparency dashboard  →</a>
</section>

<section class="panel panel--wide">
    <h2 class="panel__heading panel__heading--danger">Danger zone</h2>
    <div class="setting-row">
        <span class="setting-row__body">
            <span class="setting-row__title">Close account</span>
            <span class="setting-row__note">
                Ends your Mithra membership. You’ll choose what happens to your remaining points.
            </span>
        </span>
        <button type="button" class="btn btn--danger" data-modal-open="close-account">Close account…</button>
    </div>
</section>

<?php include __DIR__ . '/../../partials/modal-close-account.php'; ?>

<?php
$pageScripts = ['modal.js'];
include __DIR__ . '/../../partials/footer.php';
?>
