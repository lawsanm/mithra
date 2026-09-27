<?php

declare(strict_types=1);

/**
 * "Reset your password" dialog on the sign-in page. Figma: Common → "Forgot
 * Password — Modal" (93:315).
 *
 * Opened by the "Forgot password?" link (data-modal-open), or already open when
 * the page is /forgot-password or the form comes back with an answer. Without
 * JavaScript an open dialog shows in the page, so the form still works.
 *
 * An address with no active account is refused with a field error, so the
 * member can fix a typo instead of waiting for an email that never comes.
 *
 * @var bool        $forgotOpen   show the dialog open
 * @var array       $forgotErrors per-field messages
 * @var string      $forgotEmail  as typed, or the address the link was sent to
 * @var bool        $forgotSent   whether a request was just made
 * @var string|null $devLink      the link itself, shown only on a local install
 */

?>
<dialog class="modal modal--auth" id="forgot-password" aria-labelledby="forgot-password-title" <?= $forgotOpen ? 'open' : '' ?>>
    <div class="modal__head">
        <h2 class="modal__title" id="forgot-password-title">Reset your password</h2>
        <a class="modal__close" href="<?= base_url() ?>/login" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </a>
    </div>

    <?php if ($forgotSent): ?>
        <p class="notice notice--success" role="status">
            A reset link is on its way to <?= e($forgotEmail) ?>. It works
            for <?= e((string) PasswordResetService::LINK_TTL_MINUTES) ?> minutes, once.
        </p>
        <?php if ($devLink !== null): ?>
            <p class="notice notice--info">
                Local install — no mail server, so here is the link:
                <a class="link" href="<?= e($devLink) ?>">open the reset page</a>
            </p>
        <?php endif; ?>
        <div class="modal__footer">
            <a class="btn btn--primary" href="<?= base_url() ?>/login" data-modal-close>Back to log in</a>
        </div>
    <?php else: ?>
        <form class="stack" method="post" action="<?= base_url() ?>/forgot-password" novalidate>
            <?= csrf_field() ?>

            <p class="record-meta">
                Enter the email address on your account and we'll send a reset link.
            </p>

            <div class="field">
                <label class="field__label" for="forgot-email">Email address</label>
                <input
                    class="input"
                    type="email"
                    id="forgot-email"
                    name="email"
                    value="<?= e($forgotEmail) ?>"
                    placeholder="lawsan@email.com"
                    autocomplete="email"
                    autofocus
                    <?= isset($forgotErrors['email']) ? 'aria-invalid="true"' : '' ?>
                >
                <?= field_error($forgotErrors, 'email') ?>
            </div>


            <div class="modal__footer">
                <a class="btn btn--ghost" href="<?= base_url() ?>/login" data-modal-close>Cancel</a>
                <button class="btn btn--primary" type="submit">Send reset link</button>
            </div>
        </form>
    <?php endif; ?>
</dialog>
