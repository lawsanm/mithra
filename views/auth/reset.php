<?php

declare(strict_types=1);

/**
 * Choose a new password from an emailed link.
 *
 * @var array  $errors per-field messages, plus 'form'
 * @var string $token  the link token, posted back unchanged
 * @var bool   $valid  whether the token can still be used
 * @var array|null $flash
 */

$errors = $errors ?? [];
$token  = $token ?? '';
$valid  = $valid ?? false;

$pageTitle = 'Reset password';
$navActive = 'login';

$chrome = 'public';
include __DIR__ . '/../../partials/header.php';

?>

<form class="form-card form-card--auth" method="post" action="<?= base_url() ?>/reset-password" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <h1 class="form-card__title">Choose a new password</h1>

    <?php if (isset($errors['form'])): ?>
        <p class="notice notice--error" role="alert"><?= e($errors['form']) ?></p>
    <?php endif; ?>

    <?php if (!$valid): ?>
        <?php if (!isset($errors['form'])): ?>
            <p class="notice notice--error" role="alert">
                This reset link has expired or was already used.
            </p>
        <?php endif; ?>
        <a class="btn btn--primary btn--block" href="<?= base_url() ?>/forgot-password">Ask for a new link</a>
    <?php else: ?>
        <?php $passwordPrefix = 'reset'; include __DIR__ . '/../../partials/password-fields.php'; ?>

        <p class="field__hint">Changing your password signs you out everywhere else.</p>

        <button class="btn btn--primary btn--block" type="submit">Save new password</button>
    <?php endif; ?>

    <div class="auth-links">
        <a class="link" href="<?= base_url() ?>/login">Back to log in</a>
    </div>
</form>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
