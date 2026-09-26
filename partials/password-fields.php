<?php

declare(strict_types=1);

/**
 * "New password" + "Confirm new password", shared by the reset pages and the
 * change-password form so the rule text and field names never drift.
 *
 * @var array  $passwordErrors per-field messages; defaults to the page's $errors
 * @var string $passwordPrefix id prefix, unique per page
 */

$passwordErrors = $passwordErrors ?? $errors ?? [];
$passwordPrefix = $passwordPrefix ?? 'new';

?>
<div class="field">
    <label class="field__label" for="<?= e($passwordPrefix) ?>-password">New password</label>
    <input
        class="input"
        type="password"
        id="<?= e($passwordPrefix) ?>-password"
        name="password"
        autocomplete="new-password"
        <?= isset($passwordErrors['password']) ? 'aria-invalid="true"' : '' ?>
    >
    <?php if (isset($passwordErrors['password'])): ?>
        <span class="field__error"><?= e($passwordErrors['password']) ?></span>
    <?php else: ?>
        <span class="field__hint">
            At least <?= e((string) PasswordPolicy::MIN_LENGTH) ?> characters. A short phrase you will
            remember beats a short word you will not.
        </span>
    <?php endif; ?>
</div>

<div class="field">
    <label class="field__label" for="<?= e($passwordPrefix) ?>-password-confirmation">Confirm new password</label>
    <input
        class="input"
        type="password"
        id="<?= e($passwordPrefix) ?>-password-confirmation"
        name="password_confirmation"
        autocomplete="new-password"
        <?= isset($passwordErrors['password_confirmation']) ? 'aria-invalid="true"' : '' ?>
    >
    <?= field_error($passwordErrors, 'password_confirmation') ?>
</div>
