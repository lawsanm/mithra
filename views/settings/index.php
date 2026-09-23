<?php

declare(strict_types=1);

/**
 * Settings. Figma: "Settings" (95:163).
 *
 * Contact details are edited on My Profile; this page changes the password,
 * the receive-gifts preference (Plan §11.1) and closes the account (§17).
 *
 * @var array  $account         email, mobile, password_age
 * @var bool   $receiveGifts    whether other members may send gifts
 * @var string $remainingPoints spendable balance, for the closure modal
 * @var array  $closureBlockers reasons the account cannot close yet
 * @var array  $errors          per-field messages from the last form
 * @var bool   $openClose       reopen the closure dialog after a failed attempt
 * @var array|null $flash
 */

$errors          = $errors ?? [];
$closureBlockers = $closureBlockers ?? [];
$openClose       = $openClose ?? false;
$returnTo        = '/settings';

$pageTitle = 'Settings';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="page-header__title">Settings</h1>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<section class="panel panel--wide">
    <h2 class="panel__heading">Account details</h2>
    <div class="facts">
        <span class="fact">
            <span class="fact__label">Email</span>
            <span class="fact__value"><?= e($account['email']) ?></span>
        </span>
        <span class="fact">
            <span class="fact__label">Mobile number</span>
            <span class="fact__value"><?= e($account['mobile']) ?></span>
        </span>
        <span class="fact">
            <span class="fact__label">Password</span>
            <span class="fact__value"><?= e($account['password_age']) ?></span>
        </span>
    </div>
    <a class="link panel__link" href="<?= base_url() ?>/profile">Edit name, mobile, email or address  →</a>
</section>

<?php include __DIR__ . '/../../partials/change-password-form.php'; ?>

<form class="panel panel--wide" method="post" action="<?= base_url() ?>/settings/preferences">
    <?= csrf_field() ?>

    <h2 class="panel__heading">Preferences</h2>

    <div class="setting-row">
        <span class="setting-row__body">
            <label class="setting-row__title" for="pref-receive-gifts">Receive gifts</label>
            <span class="setting-row__note">
                Allow other members to send you point gifts. Turning this off hides you from the
                gift recipient list.
            </span>
        </span>
        <input
            class="toggle"
            type="checkbox"
            id="pref-receive-gifts"
            name="receive_gifts"
            value="1"
            <?= $receiveGifts ? 'checked' : '' ?>
        >
    </div>

    <button class="btn btn--primary" type="submit">Save preferences</button>
</form>

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
    <?php foreach (['form', 'closure_type', 'close_password'] as $closeField): ?>
        <?php if (isset($errors[$closeField])): ?>
            <p class="notice notice--error" role="alert"><?= e($errors[$closeField]) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>
</section>

<?php include __DIR__ . '/../../partials/modal-close-account.php'; ?>

<?php
$pageScripts = ['modal.js'];
include __DIR__ . '/../../partials/footer.php';
?>
