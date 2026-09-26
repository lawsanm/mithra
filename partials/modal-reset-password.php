<?php

declare(strict_types=1);

/**
 * "Choose a new password" dialog on the sign-in page, reached from an emailed
 * reset link. Figma: Common → "Reset Password — New Password" (646:764).
 *
 * Rendered only on /reset-password, already open. Without JavaScript an open
 * dialog shows in the page, so the form still works.
 *
 * @var array  $resetErrors per-field messages, plus 'form'
 * @var string $resetToken  the link token, posted back unchanged
 * @var bool   $resetValid  whether the token can still be used
 */

?>
<dialog class="modal modal--auth" id="reset-password" aria-labelledby="reset-password-title" open>
    <div class="modal__head">
        <h2 class="modal__title" id="reset-password-title">Choose a new password</h2>
        <a class="modal__close" href="<?= base_url() ?>/login" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </a>
    </div>

    <?php if (isset($resetErrors['form'])): ?>
        <p class="notice notice--error" role="alert"><?= e($resetErrors['form']) ?></p>
    <?php endif; ?>

    <?php if (!$resetValid): ?>
        <?php if (!isset($resetErrors['form'])): ?>
            <p class="notice notice--error" role="alert">
                This reset link has expired or was already used.
            </p>
        <?php endif; ?>
        <div class="modal__footer">
            <a class="btn btn--ghost" href="<?= base_url() ?>/login" data-modal-close>Cancel</a>
            <a class="btn btn--primary" href="<?= base_url() ?>/forgot-password" autofocus>Ask for a new link</a>
        </div>
    <?php else: ?>
        <form class="stack" method="post" action="<?= base_url() ?>/reset-password" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($resetToken) ?>">

            <p class="record-meta">
                Your reset link is verified. Changing your password signs you out everywhere else.
            </p>

            <?php $passwordErrors = $resetErrors; $passwordPrefix = 'reset'; include __DIR__ . '/password-fields.php'; ?>

            <div class="modal__footer">
                <a class="btn btn--ghost" href="<?= base_url() ?>/login" data-modal-close>Cancel</a>
                <button class="btn btn--primary" type="submit">Save new password</button>
            </div>
        </form>
    <?php endif; ?>
</dialog>
