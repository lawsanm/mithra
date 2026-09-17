<?php

declare(strict_types=1);

/**
 * Log in. Figma: Common → "Login" (93:282), with the forgot-password (93:315)
 * and new-password (646:764) dialogs over it.
 *
 * The form works without JavaScript and re-renders with the identifier the
 * member typed; the password is never sent back to the browser.
 *
 * @var array       $errors       per-field messages, plus 'form' for a refusal that
 *                                belongs to the attempt rather than to one field
 * @var string      $identifier   email or mobile number, as typed
 * @var string|null $dialog       'forgot' or 'reset' to show that dialog open
 * @var array       $forgotErrors see partials/modal-forgot-password.php
 * @var string      $forgotEmail
 * @var bool        $forgotSent
 * @var string|null $devLink
 * @var array       $resetErrors  see partials/modal-reset-password.php
 * @var string      $resetToken
 * @var bool        $resetValid
 * @var array|null  $flash
 */

$errors       = $errors ?? [];
$identifier   = $identifier ?? '';
$dialog       = $dialog ?? null;
$forgotErrors = $forgotErrors ?? [];
$forgotEmail  = $forgotEmail ?? '';
$forgotSent   = $forgotSent ?? false;
$devLink      = $devLink ?? null;
$resetErrors  = $resetErrors ?? [];
$resetToken   = $resetToken ?? '';
$resetValid   = $resetValid ?? false;

$forgotOpen = $dialog === 'forgot';

$pageTitle = match ($dialog) {
    'forgot' => 'Forgot password',
    'reset'  => 'Reset password',
    default  => 'Log in',
};
$navActive = 'login';
$pageClass = 'page--auth';

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
            placeholder="lawsan@email.com"
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
        <a class="link" href="<?= base_url() ?>/forgot-password" data-modal-open="forgot-password">Forgot password?</a>
        <a class="link" href="<?= base_url() ?>/register">New here? Register</a>
    </div>
</form>

<?php include __DIR__ . '/../../partials/modal-forgot-password.php'; ?>

<?php if ($dialog === 'reset'): ?>
    <?php include __DIR__ . '/../../partials/modal-reset-password.php'; ?>
<?php endif; ?>

<?php $pageScripts = ['modal.js']; ?>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
