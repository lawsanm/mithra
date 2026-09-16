<?php

declare(strict_types=1);

/**
 * "Close your account" modal. Figma: "Close Account — Modal" (95:215).
 *
 * A real POST form (Plan §17): the member picks Type A or Type B and confirms
 * with their password. Open with a trigger carrying
 * data-modal-open="close-account"; the host page must also load /js/modal.js.
 * Without JavaScript the <dialog> stays closed, so the host page repeats any
 * refusal in its own Danger zone.
 *
 * @var string $remainingPoints balance the member must dispose of
 * @var array  $closureBlockers reasons closing is refused right now
 * @var array  $errors          per-field messages from the last attempt
 * @var bool   $openClose       show the dialog open, after a failed attempt
 */

$remainingPoints = $remainingPoints ?? '0 pts';
$closureBlockers = $closureBlockers ?? [];
$errors          = $errors ?? [];
$openClose       = $openClose ?? false;

$closureOptions = [
    [
        'value' => 'standard',
        'title' => 'Type A — Standard closure',
        'note'  => 'Your remaining points move to the Retired Pool and are recycled into the community '
                 . 'Sponsor Pool. Listings are removed and your profile is archived.',
    ],
    [
        'value' => 'parting_gift',
        'title' => 'Type B — Parting gift',
        'note'  => 'Your remaining points move to the Aid Pool to help members in need. Recorded on the '
                 . 'Transparency Dashboard.',
    ],
];

?>
<dialog class="modal" id="close-account" aria-labelledby="close-account-title" <?= $openClose ? 'open' : '' ?>>
    <div class="modal__head">
        <h2 class="modal__title" id="close-account-title">Close your account</h2>
        <button class="modal__close" type="button" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </button>
    </div>

    <p class="record-meta">
        You have <?= e($remainingPoints) ?> remaining. Choose what happens to them:
    </p>

    <?php if ($closureBlockers !== []): ?>
        <div class="notice notice--warning">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-alert-triangle"></use></svg>
            <span>
                <?php foreach ($closureBlockers as $blocker): ?>
                    <?= e($blocker) ?>
                <?php endforeach; ?>
            </span>
        </div>
        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Keep my account</button>
        </div>
    <?php else: ?>
        <form class="stack" method="post" action="<?= base_url() ?>/settings/close-account" novalidate>
            <?= csrf_field() ?>

            <?php if (isset($errors['form'])): ?>
                <p class="notice notice--error" role="alert"><?= e($errors['form']) ?></p>
            <?php endif; ?>

            <?php foreach ($closureOptions as $index => $option): ?>
                <label class="choice">
                    <input
                        class="choice__input"
                        type="radio"
                        name="closure_type"
                        value="<?= e($option['value']) ?>"
                        <?= $index === 0 ? 'checked' : '' ?>
                    >
                    <span class="choice__body">
                        <span class="choice__title"><?= e($option['title']) ?></span>
                        <span class="choice__note"><?= e($option['note']) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
            <?= field_error($errors, 'closure_type') ?>

            <div class="field">
                <label class="field__label" for="close-password">Your password, to confirm</label>
                <input
                    class="input"
                    type="password"
                    id="close-password"
                    name="password"
                    autocomplete="current-password"
                    <?= isset($errors['close_password']) ? 'aria-invalid="true"' : '' ?>
                >
                <?= field_error($errors, 'close_password') ?>
            </div>

            <p class="notice notice--warning">
                <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-alert-triangle"></use></svg>
                Closure is permanent — re-joining requires full verification again.
            </p>

            <div class="modal__footer">
                <button class="btn btn--ghost" type="button" data-modal-close>Keep my account</button>
                <button class="btn btn--danger" type="submit">Close my account</button>
            </div>
        </form>
    <?php endif; ?>
</dialog>
