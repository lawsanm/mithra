<?php

declare(strict_types=1);

/**
 * "Raise a damage claim" modal. Figma: "Raise Damage Claim — Modal" (73:85).
 *
 * Open with a trigger carrying data-modal-open="damage-claim"; the host page
 * must also load /js/modal.js. Without JavaScript the trigger's link reloads
 * the page with ?claim=1 and the dialog renders open.
 *
 * @var array $claimItem  title, party, photo, booking_id, declared_value, simple_cap
 * @var bool  $claimOpen  render it open
 */

$claimItem = ($claimItem ?? []) + ['title' => '', 'party' => '', 'photo' => null, 'booking_id' => 0, 'declared_value' => 0, 'simple_cap' => 0];

$severities = [
    ['value' => 'minor',      'label' => 'Minor'],
    ['value' => 'moderate',   'label' => 'Moderate'],
    ['value' => 'major',      'label' => 'Major'],
    ['value' => 'total_loss', 'label' => 'Total loss'],
];

?>
<dialog class="modal modal--lg" id="damage-claim" aria-labelledby="damage-claim-title"<?= !empty($claimOpen) ? ' open' : '' ?>>
    <div class="modal__head">
        <h2 class="modal__title" id="damage-claim-title">Raise a damage claim</h2>
        <button class="modal__close" type="button" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </button>
    </div>

    <div class="media">
        <?php $photoUrl = $claimItem['photo']; $photoTitle = $claimItem['title']; $photoClass = 'thumb--modal'; include __DIR__ . '/item-photo.php'; ?>
        <span class="media__body">
            <span class="media__title"><?= e($claimItem['title']) ?></span>
            <span class="media__meta"><?= e($claimItem['party']) ?></span>
        </span>
    </div>

    <form class="stack" method="post" action="<?= base_url() ?>/bookings/<?= e((string) $claimItem['booking_id']) ?>/claims" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <fieldset>
            <legend class="field__label">Severity</legend>
            <div class="filter-pills">
                <?php foreach ($severities as $index => $severity): ?>
                    <label class="pill">
                        <input class="visually-hidden" type="radio" name="severity" value="<?= e($severity['value']) ?>" <?= $index === 0 ? 'checked' : '' ?>>
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
            <input class="input" type="number" id="claim-amount" name="amount">
            <span class="field__hint">
                Up to the declared value, <?= e(number_format((int) $claimItem['declared_value'])) ?> pts. A minor claim of
                <?= e(number_format((int) $claimItem['simple_cap'])) ?> pts or less goes straight to the borrower; anything
                else goes to your moderator.
            </span>
        </div>

        <div class="field">
            <label class="field__label" for="claim-description">What happened?</label>
            <input class="input" type="text" id="claim-description" name="description" placeholder="Chuck no longer grips bits — worked at handover…">
        </div>

        <p class="field__label">Evidence photos</p>

        <label class="upload-drop">
            <span class="upload-drop__glyph" aria-hidden="true">＋</span>
            <span data-upload-name>Upload 1–5 photos of the damage</span>
            <input class="visually-hidden" type="file" name="evidence[]" accept="image/jpeg,image/png,image/webp" multiple>
        </label>

        <p class="notice notice--warning">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-alert-triangle"></use></svg>
            False or inflated claims lower your trust score and can forfeit your conduct standing.
        </p>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Submit claim</button>
        </div>
    </form>
</dialog>
