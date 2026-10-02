<?php

declare(strict_types=1);

/**
 * "Request to Borrow" modal. Figma: "Request to Borrow — Modal" (69:119).
 *
 * Include from a page that also loads /js/modal.js, and open it with a trigger
 * carrying data-modal-open="request-borrow". Without JavaScript the trigger's
 * link reloads the page with ?request=1 and the dialog renders open.
 *
 * @var array $item      id, title, owner, owner_meta, photos
 * @var array $pricing   rate options: value, title, total, selected, recommended
 * @var array $quote     BookingService::quote() plus from, to
 * @var bool  $modalOpen render it open
 */

?>
<dialog class="modal" id="request-borrow" aria-labelledby="request-borrow-title"<?= !empty($modalOpen) ? ' open' : '' ?>>
    <div class="modal__head">
        <h2 class="modal__title" id="request-borrow-title">Request to Borrow</h2>
        <button class="modal__close" type="button" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </button>
    </div>

    <div class="media">
        <?php $photoUrl = $item['photos'][0] ?? null; $photoTitle = $item['title']; $photoClass = 'thumb--modal'; include __DIR__ . '/item-photo.php'; ?>
        <span class="media__body">
            <span class="media__title"><?= e($item['title']) ?></span>
            <span class="media__meta"><?= e($item['owner_meta']) ?></span>
        </span>
    </div>

    <form class="stack" method="post" action="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>/borrow" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="from_date" value="<?= e($quote['from']) ?>">
        <input type="hidden" name="to_date" value="<?= e($quote['to']) ?>">

        <p class="line-item">
            <span class="line-item__label">Dates</span>
            <span class="line-item__value">
                <?= e(date('j M Y', strtotime($quote['from']))) ?> – <?= e(date('j M Y', strtotime($quote['to']))) ?>
                · <?= e($quote['days'] . ' day' . ($quote['days'] === 1 ? '' : 's')) ?>
            </span>
        </p>

        <p class="field__label">Choose a pricing option</p>

        <?php foreach ($pricing as $option): ?>
            <label class="choice choice--compact<?= !empty($option['recommended']) ? ' choice--recommended' : '' ?>">
                <input class="choice__input" type="radio" name="basis" value="<?= e($option['value']) ?>" <?= $option['selected'] ? 'checked' : '' ?>>
                <span class="choice__body">
                    <span class="choice__title"><?= e($option['title']) ?></span>
                    <span class="choice__note"><?= e($option['total']) ?></span>
                </span>
                <?php if (!empty($option['recommended'])): ?>
                    <span class="badge badge--success choice__aside">
                        <span aria-hidden="true">✓</span>
                        <?= e($option['recommended']) ?>
                    </span>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>

        <div class="field">
            <label class="field__label" for="modal-message">Message to <?= e($item['owner']) ?> (optional)</label>
            <input class="input" type="text" id="modal-message" name="message"
                placeholder="Hi! I’d like to borrow this for a shelving project…">
        </div>

        <p class="line-item">
            <span class="line-item__label">Held in escrow on acceptance</span>
            <strong class="line-item__value total-row__value"><?= e(number_format($quote['total'])) ?> pts</strong>
        </p>
        <p class="field__hint">
            <?= e($quote['charge'] . ' pts rental charge and a ' . $quote['buffer'] . '-point late buffer, returned if the item comes back on time. Nothing moves until the lender accepts.') ?>
        </p>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Send request</button>
        </div>
    </form>
</dialog>
