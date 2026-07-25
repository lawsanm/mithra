<?php

declare(strict_types=1);

/**
 * "Rate your experience" modal. Figma: "Rate & Review — Modal" (73:128).
 *
 * Open with a trigger carrying data-modal-open="rate-review"; the host page
 * must also load /js/modal.js.
 *
 * @var array $ratee     initials, name, booking line
 * @var int   $rating    stars pre-selected
 * @var array $quickTags label, value, selected
 */

$ratee = ($ratee ?? []) + [
    'initials' => 'TM',
    'name'     => 'T.H.K. Madushan',
    'booking'  => 'Bosch Cordless Drill  ·  12–17 Jul',
];

$rating = $rating ?? 4;

$quickTags = $quickTags ?? [
    ['value' => 'as-described',  'label' => 'Item as described',   'selected' => true],
    ['value' => 'smooth',        'label' => 'Smooth handover',     'selected' => true],
    ['value' => 'communication', 'label' => 'Great communication', 'selected' => false],
    ['value' => 'flexible',      'label' => 'Flexible timing',     'selected' => false],
];

?>
<dialog class="modal modal--sm" id="rate-review" aria-labelledby="rate-review-title">
    <div class="modal__head">
        <h2 class="modal__title" id="rate-review-title">Rate your experience</h2>
        <button class="modal__close" type="button" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </button>
    </div>

    <div class="media">
        <span class="avatar avatar--lg"><?= e($ratee['initials']) ?></span>
        <span class="media__body">
            <span class="media__title"><?= e($ratee['name']) ?></span>
            <span class="media__meta"><?= e($ratee['booking']) ?></span>
        </span>
    </div>

    <form class="stack" method="post" action="/ratings">
        <?= csrf_field() ?>

        <fieldset class="rating">
            <legend class="visually-hidden">Rating out of 5</legend>
            <?php for ($star = 5; $star >= 1; $star--): ?>
                <input
                    type="radio"
                    id="star-<?= e((string) $star) ?>"
                    name="rating"
                    value="<?= e((string) $star) ?>"
                    <?= $star === $rating ? 'checked' : '' ?>
                    required
                >
                <label for="star-<?= e((string) $star) ?>">
                    <span aria-hidden="true">★</span>
                    <span class="visually-hidden"><?= e((string) $star) ?> stars</span>
                </label>
            <?php endfor; ?>
        </fieldset>

        <fieldset>
            <legend class="field__label">Quick tags</legend>
            <div class="tag-list">
                <?php foreach ($quickTags as $tag): ?>
                    <label class="tag">
                        <input
                            class="visually-hidden"
                            type="checkbox"
                            name="tags[]"
                            value="<?= e($tag['value']) ?>"
                            <?= $tag['selected'] ? 'checked' : '' ?>
                        >
                        <?= e($tag['label']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="field">
            <label class="field__label" for="review-text">Review (optional)</label>
            <input
                class="input"
                type="text"
                id="review-text"
                name="review"
                placeholder="Drill was in great shape, batteries fully charged…"
            >
        </div>

        <p class="field__hint">
            Ratings feed both members’ trust scores and can’t be edited later.
        </p>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Skip</button>
            <button class="btn btn--primary" type="submit">Submit review</button>
        </div>
    </form>
</dialog>
