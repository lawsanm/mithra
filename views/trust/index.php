<?php

declare(strict_types=1);

/**
 * Trust score breakdown. Figma: "Trust Score — Breakdown" (77:174).
 *
 * @var array $score   value, badge, meta line
 * @var array $factors the five weighted factors: name, weight, percent, note
 */

// Sample view data — replaced by the controller once TrustController lands.
$score ??= [
    // Computed 0.40·96 + 0.20·46 + 0.20·96 + 0.10·100 + 0.10·30 = 79.8, blended
    // with the neutral 50: (10·50 + 23·79.8) ÷ (10 + 23) = 70.8 → 71 (Plan §6.3.2).
    'value' => '71',
    'badge' => ['success', '✓', 'Good standing'],
    'meta'  => 'Out of 100  ·  computed 80, blended toward 50 over 23 completed transactions  ·  Kollupitiya',
];

$factors ??= [
    [
        'name'    => 'Average rating (R)',
        'weight'  => '40%',
        'percent' => 96,
        'note'    => '4.8 / 5.0  →  96',
    ],
    [
        'name'    => 'Completed transactions (V)',
        'weight'  => '20%',
        'percent' => 46,
        'note'    => '23 of 50 (capped)  →  46',
    ],
    [
        'name'    => 'Return reliability (L)',
        'weight'  => '20%',
        'percent' => 96,
        'note'    => '96% on-time · excl. donations  →  96',
    ],
    [
        'name'    => 'Member tenure (T)',
        'weight'  => '10%',
        'percent' => 100,
        'note'    => '12 of 12 months (capped)  →  100',
    ],
    [
        'name'    => 'Community contribution (C)',
        'weight'  => '10%',
        'percent' => 30,
        'note'    => '3 donations  →  30',
    ],
];

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
            <progress
                class="meter"
                id="<?= e($meterId) ?>"
                max="100"
                value="<?= e((string) $factor['percent']) ?>"
            ><?= e((string) $factor['percent']) ?>%</progress>
            <span class="factor-row__note"><?= e($factor['note']) ?></span>
        </div>
    <?php endforeach; ?>
</section>

<p class="notice notice--info">
    <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
    Weighted score = 0.40R + 0.20V + 0.20L + 0.10T + 0.10C — each factor scaled to 0–100.
    With fewer than 10 transactions, scores lean on the community average so new members
    aren’t unfairly penalised.
</p>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
