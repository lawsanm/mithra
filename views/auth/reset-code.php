<?php

declare(strict_types=1);

/**
 * Choose a new password with a code the division moderator (or the Admin)
 * issued in person.
 *
 * @var array  $errors     per-field messages, plus 'form'
 * @var string $identifier email or mobile number, as typed
 * @var array|null $flash
 */

$errors     = $errors ?? [];
$identifier = $identifier ?? '';

$pageTitle = 'Reset password with a code';
$navActive = 'login';

$pageClass = 'page--auth';
$chrome = 'public';
include __DIR__ . '/../../partials/header.php';

?>

<form class="form-card form-card--auth" method="post" action="<?= base_url() ?>/reset-password/code" novalidate>
    <?= csrf_field() ?>

    <h1 class="form-card__title">Reset with a code</h1>

    <p class="field__hint">
        Your division moderator gives you this code after checking your NIC. It works once,
        for <?= e((string) intdiv(PasswordResetService::CODE_TTL_MINUTES, 60)) ?> hours.
    </p>

    <?php if (isset($errors['form'])): ?>
        <p class="notice notice--error" role="alert"><?= e($errors['form']) ?></p>
    <?php endif; ?>

    <div class="field">
        <label class="field__label" for="code-identifier">Email or mobile number you sign in with</label>
        <input
            class="input"
            type="text"
            id="code-identifier"
            name="identifier"
            value="<?= e($identifier) ?>"
            autocomplete="username"
            autocapitalize="none"
            spellcheck="false"
            <?= isset($errors['identifier']) ? 'aria-invalid="true"' : '' ?>
        >
        <?= field_error($errors, 'identifier') ?>
    </div>

    <div class="field">
        <label class="field__label" for="code-value">Reset code</label>
        <input
            class="input"
            type="text"
            id="code-value"
            name="code"
            placeholder="ABCDE-FGHJK"
            autocomplete="one-time-code"
            autocapitalize="characters"
            spellcheck="false"
            <?= isset($errors['code']) ? 'aria-invalid="true"' : '' ?>
        >
        <?= field_error($errors, 'code') ?>
    </div>

    <?php $passwordPrefix = 'code'; include __DIR__ . '/../../partials/password-fields.php'; ?>

    <button class="btn btn--primary btn--block" type="submit">Save new password</button>

    <div class="auth-links">
        <a class="link" href="<?= base_url() ?>/forgot-password">Have an email? Get a link instead</a>
        <a class="link" href="<?= base_url() ?>/login">Back to log in</a>
    </div>
</form>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
