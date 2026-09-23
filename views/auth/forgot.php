<?php

declare(strict_types=1);

/**
 * Forgot password — ask for a reset link by email, or go to the code page.
 *
 * The answer after submitting is the same whether or not the address has an
 * account, so this page cannot be used to find out who is a member.
 *
 * @var array       $errors  per-field messages
 * @var string      $email   as typed, after a validation failure
 * @var bool        $sent    whether a request was just made
 * @var string|null $devLink the link itself, shown only on a local install
 * @var array|null  $flash
 */

$errors  = $errors ?? [];
$email   = $email ?? '';
$sent    = $sent ?? false;
$devLink = $devLink ?? null;

$pageTitle = 'Forgot password';
$navActive = 'login';

$chrome = 'public';
include __DIR__ . '/../../partials/header.php';

?>

<form class="form-card form-card--auth" method="post" action="<?= base_url() ?>/forgot-password">
    <?= csrf_field() ?>

    <h1 class="form-card__title">Forgot your password?</h1>

    <?php include __DIR__ . '/../../partials/flash.php'; ?>

    <?php if ($sent): ?>
        <p class="notice notice--success" role="status">
            If an active account uses that email address, a reset link is on its way. It works
            for <?= e((string) PasswordResetService::LINK_TTL_MINUTES) ?> minutes, once.
        </p>
        <?php if ($devLink !== null): ?>
            <p class="notice notice--info">
                Local install — no mail server, so here is the link:
                <a class="link" href="<?= e($devLink) ?>">open the reset page</a>
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <div class="field">
        <label class="field__label" for="forgot-email">Email address</label>
        <input
            class="input"
            type="email"
            id="forgot-email"
            name="email"
            value="<?= e($email) ?>"
            maxlength="150"
            autocomplete="email"
            required
            <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>
        >
        <?php if (isset($errors['email'])): ?>
            <span class="field__error"><?= e($errors['email']) ?></span>
        <?php endif; ?>
    </div>

    <button class="btn btn--primary btn--block" type="submit">Email me a reset link</button>

    <p class="field__hint">
        Signed up with a mobile number only? Ask your division moderator for a one-time reset code —
        they will check your NIC first — then
        <a class="link" href="<?= base_url() ?>/reset-password/code">enter the code here</a>.
    </p>

    <div class="auth-links">
        <a class="link" href="<?= base_url() ?>/login">Back to log in</a>
    </div>
</form>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
