<?php

declare(strict_types=1);

/**
 * The change-password form, posted to /account/password from any page that
 * embeds it (Settings, the Admin's Security page, the stand-alone page).
 *
 * @var array  $errors   per-field messages
 * @var string $returnTo whitelisted path to come back to after saving
 */

$errors   = $errors ?? [];
$returnTo = $returnTo ?? '/account/password';

?>
<form class="panel panel--wide" method="post" action="<?= base_url() ?>/account/password">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">

    <h2 class="panel__heading">Change password</h2>

    <div class="field">
        <label class="field__label" for="current-password">Current password</label>
        <input
            class="input"
            type="password"
            id="current-password"
            name="current_password"
            autocomplete="current-password"
            required
            <?= isset($errors['current_password']) ? 'aria-invalid="true"' : '' ?>
        >
        <?php if (isset($errors['current_password'])): ?>
            <span class="field__error"><?= e($errors['current_password']) ?></span>
        <?php endif; ?>
    </div>

    <?php $passwordPrefix = 'change'; include __DIR__ . '/password-fields.php'; ?>

    <p class="field__hint">Saving signs you out on every other device.</p>

    <button class="btn btn--primary" type="submit">Update password</button>
</form>
