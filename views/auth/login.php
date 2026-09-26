<?php

declare(strict_types=1);

/**
 * Log in. Figma: Common → "Login" (93:282).
 *
 * The form works without JavaScript and re-renders with the identifier the
 * member typed; the password is never sent back to the browser.
 *
 * @var array  $errors     per-field messages, plus 'form' for a refusal that
 *                         belongs to the attempt rather than to one field
 * @var string $identifier email or mobile number, as typed
 * @var array|null $flash
 */

$errors     = $errors ?? [];
$identifier = $identifier ?? '';

$pageTitle = 'Log in';
$navActive = 'login';

$chrome = 'public';
include __DIR__ . '/../../partials/header.php';

?>

<form class="form-card form-card--auth" method="post" action="<?= base_url() ?>/login" novalidate>
    <?= csrf_field() ?>

    <h1 class="form-card__title">Welcome back</h1>

    <?php include __DIR__ . '/../../partials/flash.php'; ?>

    <?php if (isset($errors['form'])): ?>
        <p class="notice notice--error" role="alert">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            <?= e($errors['form']) ?>
        </p>
    <?php endif; ?>

    <div class="field">
        <label class="field__label" for="login-identifier">Email or mobile number</label>
        <input
            class="input"
            type="text"
            id="login-identifier"
            name="identifier"
            value="<?= e($identifier) ?>"
            placeholder="lawsanm@gmail.com"
            autocomplete="username"
            autocapitalize="none"
            spellcheck="false"
            <?= isset($errors['identifier']) ? 'aria-invalid="true"' : '' ?>
        >
        <?= field_error($errors, 'identifier') ?>
    </div>

    <div class="field">
        <label class="field__label" for="login-password">Password</label>
        <input
            class="input"
            type="password"
            id="login-password"
            name="password"
            autocomplete="current-password"
            <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?>
        >
        <?= field_error($errors, 'password') ?>
    </div>

    <button class="btn btn--primary btn--block" type="submit">Log in</button>

    <div class="auth-links">
        <a class="link" href="<?= base_url() ?>/forgot-password">Forgot password?</a>
        <a class="link" href="<?= base_url() ?>/register">New here? Register</a>
    </div>
</form>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
