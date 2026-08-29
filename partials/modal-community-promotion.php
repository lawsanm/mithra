<?php

declare(strict_types=1);

/**
 * "Make X your home community?" modal.
 * Figma: "Community Promotion — Modal" (77:153).
 *
 * Open with a trigger carrying data-modal-open="community-promotion"; the host
 * page must also load /js/modal.js.
 *
 * @var array $promotion home and temporary community lines
 */

$promotion = ($promotion ?? []) + [
    'temporary_name' => 'Dehiwala',
    'home_line'      => 'Kollupitiya  ·  member since 2025',
    'temporary_line' => 'Dehiwala  ·  joined 17 Jul 2026',
];

?>
<dialog class="modal modal--sm" id="community-promotion" aria-labelledby="community-promotion-title">
    <div class="modal__head">
        <h2 class="modal__title" id="community-promotion-title">
            Make <?= e($promotion['temporary_name']) ?> your home community?
        </h2>
        <button class="modal__close" type="button" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </button>
    </div>

    <p class="line-item">
        <span class="line-item__label">Current home</span>
        <span class="line-item__value"><?= e($promotion['home_line']) ?></span>
    </p>

    <p class="line-item">
        <span class="line-item__label">Temporary</span>
        <span class="line-item__value"><?= e($promotion['temporary_line']) ?></span>
    </p>

    <p class="notice notice--warning">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-alert-triangle"></use></svg>
        Promoting swaps the two: <?= e($promotion['temporary_name']) ?> becomes your home
        community and your Kollupitiya membership ends. Your listings there are unlisted
        and active bookings must complete first. This can’t be undone from this screen.
    </p>

    <form class="modal__footer" method="post" action="<?= base_url() ?>/community/promote">
        <?= csrf_field() ?>
        <button class="btn btn--ghost" type="button" data-modal-close>Keep as temporary</button>
        <button class="btn btn--primary" type="submit">Confirm promotion</button>
    </form>
</dialog>
