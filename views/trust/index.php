<?php

declare(strict_types=1);

/**
 * Trust score breakdown. Figma: "Trust Score — Breakdown" (77:174).
 *
 * @var array  $score     value, badge, meta line
 * @var array  $factors   the five weighted factors: name, weight, percent, note
 * @var array  $penalties label => count × points
 * @var string $blend     how the weighted score became the final one
 */

$pageTitle = 'My trust score';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="detail__title">My trust score</h1>

<section class="panel">
    <h2 class="visually-hidden">Current score</h2>
    <div class="score-hero">
        <strong class="score-hero__value"><?= e($score['value']) ?></strong>
        <div class="score-hero__body">
            <span class="badge badge--<?= e($score['badge'][0]) ?>">
                <span aria-hidden="true"><?= e($score['badge'][1]) ?></span>
                <?= e($score['badge'][2]) ?>
            </span>
            <span class="score-hero__meta"><?= e($score['meta']) ?></span>
        </div>
    </div>
</section>

<section class="panel">
    <h2 class="panel__title">How your score is built  ·  5 weighted factors</h2>

    <?php foreach ($factors as $index => $factor): ?>
        <?php $meterId = 'factor-' . $index; ?>
        <div class="factor-row">
            <label class="factor-row__name" for="<?= e($meterId) ?>"><?= e($factor['name']) ?></label>
            <span class="weight-pill"><?= e($factor['weight']) ?></span>
            <progress class="meter" id="<?= e($meterId) ?>" max="100" value="<?= e((string) $factor['percent']) ?>"><?= e((string) $factor['percent']) ?>%</progress>
            <span class="factor-row__note"><?= e($factor['note']) ?></span>
        </div>
    <?php endforeach; ?>

    <p class="record-meta"><?= e($blend) ?></p>
</section>

<section class="panel">
    <h2 class="panel__title">Penalties</h2>
    <dl class="facts">
        <?php foreach ($penalties as $label => $value): ?>
            <div class="fact"><dt class="fact__label"><?= e($label) ?></dt><dd class="fact__value"><?= e($value) ?></dd></div>
        <?php endforeach; ?>
    </dl>
</section>

<p class="notice notice--info">
    <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
    Weighted score = 0.40R + 0.20V + 0.20L + 0.10T + 0.10C — each factor scaled to 0–100.
    With few transactions, scores lean on the community midpoint of 50 so new members
    aren’t unfairly penalised. Your score is recalculated after every completed booking,
    donation, damage resolution and rating, and every night.
</p>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
