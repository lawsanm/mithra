<?php

declare(strict_types=1);

/**
 * Trust score breakdown. Figma: "Trust Score — Breakdown" (77:174).
 *
 * @var array $score   value, badge, meta line
 * @var array $factors the five weighted factors: name, weight, percent, note
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
    <h2 class="panel__title">Your recorded activity</h2>
    <dl class="facts">
        <?php foreach ($trustFacts as $label => $value): ?>
            <div class="fact"><dt class="fact__label"><?= e($label) ?></dt><dd class="fact__value"><?= e($value) ?></dd></div>
        <?php endforeach; ?>
    </dl>
</section>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
