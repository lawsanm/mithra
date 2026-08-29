<?php

declare(strict_types=1);

/**
 * "Close your account" modal. Figma: "Close Account — Modal" (95:215).
 *
 * Open with a trigger carrying data-modal-open="close-account"; the host page
 * must also load /js/modal.js.
 *
 * @var string $remainingPoints balance the member must dispose of
 * @var array  $closureOptions  Type A / Type B choices
 */

$remainingPoints = $remainingPoints ?? '1,250 pts';

$closureOptions = $closureOptions ?? [
    [
        'value'    => 'standard',
        'title'    => 'Type A — Standard closure',
        'note'     => 'Your remaining points return to the community Reserve Pool. Listings '
                    . 'are removed and your profile is archived.',
        'selected' => true,
    ],
    [
        'value'    => 'donation',
        'title'    => 'Type B — Donation parting gift',
        'note'     => 'Donate your remaining points to the Aid Pool to help members in need. '
                    . 'Recorded on the Transparency Dashboard.',
        'selected' => false,
    ],
];

?>
<dialog class="modal" id="close-account" aria-labelledby="close-account-title">
    <div class="modal__head">
        <h2 class="modal__title" id="close-account-title">Close your account</h2>
        <button class="modal__close" type="button" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </button>
    </div>

    <p class="record-meta">
        You have <?= e($remainingPoints) ?> remaining. Choose what happens to them:
    </p>

    <form class="stack" method="post" action="<?= base_url() ?>/settings/close-account">
        <?= csrf_field() ?>

        <?php foreach ($closureOptions as $option): ?>
            <label class="choice">
                <input
                    class="choice__input"
                    type="radio"
                    name="closure_type"
                    value="<?= e($option['value']) ?>"
                    <?= $option['selected'] ? 'checked' : '' ?>
                    required
                >
                <span class="choice__body">
                    <span class="choice__title"><?= e($option['title']) ?></span>
                    <span class="choice__note"><?= e($option['note']) ?></span>
                </span>
            </label>
        <?php endforeach; ?>

        <p class="notice notice--warning">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-alert-triangle"></use></svg>
            You can’t close while you have active bookings, a pending damage claim, or an
            open aid grant. Closure is permanent — re-joining requires full verification again.
        </p>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Keep my account</button>
            <button class="btn btn--primary" type="submit">Continue to confirm</button>
        </div>
    </form>
</dialog>
