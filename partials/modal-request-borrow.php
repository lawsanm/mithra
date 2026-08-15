<?php

declare(strict_types=1);

/**
 * "Request to Borrow" modal. Figma: "Request to Borrow — Modal" (69:119).
 *
 * Include from a page that also loads /js/modal.js, and open it with a trigger
 * carrying data-modal-open="request-borrow".
 *
 * @var array $item    title, owner_meta
 * @var array $pricing daily and monthly options
 * @var array $quote   from, to, escrow total
 */

// Merge key-by-key: the host page already sets $item, but not every key the
// modal needs, so ??= on the whole array would leave gaps.
$item = ($item ?? []) + [
    'title'      => 'Bosch Cordless Drill GSB 120',
    'owner'      => 'Madushan',
    'owner_meta' => 'T.H.K. Madushan  ·  Trust 96  ·  0.4 km',
];

$pricing ??= [
    [
        'value'    => 'daily',
        'title'    => 'Daily rate  ·  15 pts × 12 days',
        'total'    => 'Total 180 pts',
        'selected' => false,
    ],
    [
        'value'       => 'monthly',
        'title'       => 'Monthly rate  ·  flat',
        'total'       => 'Total 150 pts',
        'selected'    => true,
        'recommended' => 'Cheaper — save 30 pts',
    ],
];

$quote = ($quote ?? []) + [
    'from'   => '',
    'to'     => '',
    'escrow' => '150 pts',
];

?>
<dialog class="modal" id="request-borrow" aria-labelledby="request-borrow-title">
    <div class="modal__head">
        <h2 class="modal__title" id="request-borrow-title">Request to Borrow</h2>
        <button class="modal__close" type="button" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </button>
    </div>

    <div class="media">
        <span class="thumb thumb--modal"></span>
        <span class="media__body">
            <span class="media__title"><?= e($item['title']) ?></span>
            <span class="media__meta"><?= e($item['owner_meta']) ?></span>
        </span>
    </div>

    <form class="stack" method="post" action="<?= base_url() ?>/bookings">
        <?= csrf_field() ?>

        <div class="field-row">
            <div class="field">
                <label class="visually-hidden" for="modal-from">From date</label>
                <input class="input input--half" type="date" id="modal-from" name="from_date" value="<?= e($quote['from']) ?>" required>
            </div>
            <div class="field">
                <label class="visually-hidden" for="modal-to">To date</label>
                <input class="input input--half" type="date" id="modal-to" name="to_date" value="<?= e($quote['to']) ?>" required>
            </div>
        </div>

        <p class="field__label">Choose a pricing option</p>

        <?php foreach ($pricing as $option): ?>
            <label class="choice choice--compact<?= isset($option['recommended']) ? ' choice--recommended' : '' ?>">
                <input
                    class="choice__input"
                    type="radio"
                    name="pricing"
                    value="<?= e($option['value']) ?>"
                    <?= $option['selected'] ? 'checked' : '' ?>
                >
                <span class="choice__body">
                    <span class="choice__title"><?= e($option['title']) ?></span>
                    <span class="choice__note"><?= e($option['total']) ?></span>
                </span>
                <?php if (isset($option['recommended'])): ?>
                    <span class="badge badge--success choice__aside">
                        <span aria-hidden="true">✓</span>
                        <?= e($option['recommended']) ?>
                    </span>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>

        <div class="field">
            <label class="field__label" for="modal-message">
                Message to <?= e($item['owner']) ?> (optional)
            </label>
            <input
                class="input"
                type="text"
                id="modal-message"
                name="message"
                placeholder="Hi! I’d like to borrow this for a shelving project…"
            >
        </div>

        <p class="line-item">
            <span class="line-item__label">Held in escrow on acceptance</span>
            <strong class="line-item__value total-row__value"><?= e($quote['escrow']) ?></strong>
        </p>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Send request</button>
        </div>
    </form>
</dialog>
