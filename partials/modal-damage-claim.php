<?php

declare(strict_types=1);

/**
 * "Raise a damage claim" modal. Figma: "Raise Damage Claim — Modal" (73:85).
 *
 * Open with a trigger carrying data-modal-open="damage-claim"; the host page
 * must also load /js/modal.js.
 *
 * @var array $claimItem  title, party line
 * @var array $severities selectable severity levels
 * @var array $claimDraft amount, description, declared value cap
 */

$claimItem = ($claimItem ?? []) + [
    'title' => 'Bosch Cordless Drill GSB 120',
    'party' => 'Borrower: M. Lawsan  ·  returned 17 Jul',
];

$severities = $severities ?? [
    ['value' => 'minor',      'label' => 'Minor'],
    ['value' => 'moderate',   'label' => 'Moderate', 'selected' => true],
    ['value' => 'major',      'label' => 'Major'],
    ['value' => 'total-loss', 'label' => 'Total loss'],
];

$claimDraft = ($claimDraft ?? []) + [
    'amount'      => '60',
    'description' => '',
    'cap'         => 'Capped at the declared value: 300 pts.',
];

?>
<dialog class="modal modal--lg" id="damage-claim" aria-labelledby="damage-claim-title">
    <div class="modal__head">
        <h2 class="modal__title" id="damage-claim-title">Raise a damage claim</h2>
        <button class="modal__close" type="button" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </button>
    </div>

    <div class="media">
        <span class="thumb thumb--modal"></span>
        <span class="media__body">
            <span class="media__title"><?= e($claimItem['title']) ?></span>
            <span class="media__meta"><?= e($claimItem['party']) ?></span>
        </span>
    </div>

    <form class="stack" method="post" action="/bookings/1/damage-claim" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <fieldset>
            <legend class="field__label">Severity</legend>
            <div class="filter-pills">
                <?php foreach ($severities as $severity): ?>
                    <label class="pill">
                        <input
                            class="visually-hidden"
                            type="radio"
                            name="severity"
                            value="<?= e($severity['value']) ?>"
                            <?= !empty($severity['selected']) ? 'checked' : '' ?>
                        >
                        <?= e($severity['label']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <p class="field__hint">
            Reference: Minor = cosmetic  ·  Moderate = works, needs repair  ·
            Major = unusable, repairable  ·  Total loss = beyond repair
        </p>

        <div class="field">
            <label class="field__label" for="claim-amount">Claim amount (pts)</label>
            <input class="input" type="number" id="claim-amount" name="amount" value="<?= e($claimDraft['amount']) ?>" min="1" step="1" required>
            <span class="field__hint"><?= e($claimDraft['cap']) ?></span>
        </div>

        <div class="field">
            <label class="field__label" for="claim-description">What happened?</label>
            <input
                class="input"
                type="text"
                id="claim-description"
                name="description"
                value="<?= e($claimDraft['description']) ?>"
                placeholder="Chuck no longer grips bits — worked at handover…"
                required
            >
        </div>

        <p class="field__label">Evidence photos</p>

        <label class="upload-drop">
            <span class="upload-drop__glyph" aria-hidden="true">＋</span>
            <span>Upload photos of the damage</span>
            <input class="visually-hidden" type="file" name="evidence[]" accept="image/*" multiple required>
        </label>

        <p class="notice notice--warning">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-alert-triangle"></use></svg>
            The claim goes to your GN division moderator for in-person resolution. False or
            inflated claims lower your trust score and can forfeit your conduct standing.
        </p>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Submit claim</button>
        </div>
    </form>
</dialog>
