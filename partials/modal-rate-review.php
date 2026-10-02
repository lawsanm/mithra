<?php

declare(strict_types=1);

/**
 * "Rate your experience" modal. Figma: "Rate & Review — Modal" (73:128).
 *
 * Open with a trigger carrying data-modal-open="rate-review"; the host page
 * must also load /js/modal.js. The page renders it for one record at a time
 * (?rate=booking-12 or ?edit=5), open, so it also works without JavaScript.
 *
 * @var array $rateForm action, kind, record_id, editing, ratee (initials, name, booking line),
 *                      stars, tags (selected values), review
 * @var bool  $rateOpen render it open
 */

$rateForm = ($rateForm ?? []) + [
    'action' => base_url() . '/ratings', 'kind' => '', 'record_id' => 0, 'editing' => false,
    'ratee' => ['initials' => '', 'name' => '', 'booking' => ''], 'stars' => 5, 'tags' => [], 'review' => '',
];

?>
<dialog class="modal modal--sm" id="rate-review" aria-labelledby="rate-review-title"<?= !empty($rateOpen) ? ' open' : '' ?>>
    <div class="modal__head">
        <h2 class="modal__title" id="rate-review-title"><?= $rateForm['editing'] ? 'Edit your rating' : 'Rate your experience' ?></h2>
        <button class="modal__close" type="button" data-modal-close aria-label="Close">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-x"></use></svg>
        </button>
    </div>

    <div class="media">
        <span class="avatar avatar--lg"><?= e($rateForm['ratee']['initials']) ?></span>
        <span class="media__body">
            <span class="media__title"><?= e($rateForm['ratee']['name']) ?></span>
            <span class="media__meta"><?= e($rateForm['ratee']['booking']) ?></span>
        </span>
    </div>

    <form class="stack" method="post" action="<?= e($rateForm['action']) ?>" novalidate>
        <?= csrf_field() ?>
        <?php if (!$rateForm['editing']): ?>
            <input type="hidden" name="kind" value="<?= e($rateForm['kind']) ?>">
            <input type="hidden" name="record_id" value="<?= e((string) $rateForm['record_id']) ?>">
        <?php endif; ?>

        <fieldset class="rating">
            <legend class="visually-hidden">Rating out of 5</legend>
            <?php for ($star = 5; $star >= 1; $star--): ?>
                <input type="radio" id="star-<?= e((string) $star) ?>" name="rating" value="<?= e((string) $star) ?>" <?= $star === (int) $rateForm['stars'] ? 'checked' : '' ?>>
                <label for="star-<?= e((string) $star) ?>">
                    <span aria-hidden="true">★</span>
                    <span class="visually-hidden"><?= e((string) $star) ?> stars</span>
                </label>
            <?php endfor; ?>
        </fieldset>

        <fieldset>
            <legend class="field__label">Quick tags</legend>
            <div class="tag-list">
                <?php foreach (RatingService::TAGS as $value => $label): ?>
                    <label class="tag">
                        <input class="visually-hidden" type="checkbox" name="tags[]" value="<?= e($value) ?>" <?= in_array($value, $rateForm['tags'], true) ? 'checked' : '' ?>>
                        <?= e($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="field">
            <label class="field__label" for="review-text">Review (optional)</label>
            <input class="input" type="text" id="review-text" name="review" value="<?= e($rateForm['review']) ?>"
                placeholder="Drill was in great shape, batteries fully charged…">
        </div>

        <p class="field__hint">
            Ratings feed the other member’s trust score. You can change or remove yours for <?= e((string) RatingService::EDIT_DAYS) ?> days.
        </p>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Not now</button>
            <button class="btn btn--primary" type="submit"><?= $rateForm['editing'] ? 'Save rating' : 'Submit review' ?></button>
        </div>
    </form>
</dialog>
