<?php

declare(strict_types=1);

/**
 * "New password" + "Confirm new password", shared by the reset pages and the
 * change-password form so the rule text and field names never drift.
 *
 * @var array  $errors         per-field messages
 * @var string $passwordPrefix id prefix, unique per page
 */

$errors         = $errors ?? [];
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
        <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?>
    >
    <?php if (isset($errors['password'])): ?>
        <span class="field__error"><?= e($errors['password']) ?></span>
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
        <?= isset($errors['password_confirmation']) ? 'aria-invalid="true"' : '' ?>
    >
    <?= field_error($errors, 'password_confirmation') ?>
</div>
