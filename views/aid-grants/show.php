<?php

declare(strict_types=1);

/**
 * Aid grant status. Figma: "Aid Grant — Status" (76:151) and its
 * "Aid Grant — Empty / Cooling" state (76:227) — the empty state renders when
 * there is no active grant.
 *
 * @var array|null $grant   null when the member has no active grant
 * @var array      $vouch   moderator vouch shown once complete
 * @var string     $cooling cooling-period message, empty when not cooling
 */

$stages = ['Pending vouch', 'Liaison approval', 'Approved', 'In use', 'Closed'];

$pageTitle = $grant === null ? 'Aid grants' : $grant['reference'];
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<?php if ($grant === null): ?>

    <h1 class="detail__title">Aid grants</h1>

    <div class="empty-state">
        <span class="empty-state__icon">
            <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-heart"></use></svg>
        </span>
        <p class="empty-state__title">No active aid grant</p>
        <p class="empty-state__body">
            Aid grants help with essential needs when times are tight — drawn from the
            community Aid Pool funded by sponsors.
        </p>
    </div>

    <?php if ($cooling !== ''): ?>
        <p class="notice notice--warning">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-alert-triangle"></use></svg>
            <?= e($cooling) ?>
        </p>
    <?php endif; ?>

<?php else: ?>

    <header class="record-head">
        <h1 class="detail__title"><?= e($grant['reference']) ?></h1>
        <span class="badge badge--<?= e($grant['badge'][0]) ?>">
            <span aria-hidden="true"><?= e($grant['badge'][1]) ?></span>
            <?= e($grant['badge'][2]) ?>
        </span>
    </header>

    <?php
    $wizardSteps = array_combine(range(1, count($stages)), array_values($stages));
    $wizardStep  = (int) $grant['stage'];
    $wizardClass = 'wizard--compact';
    include __DIR__ . '/../../partials/wizard-steps.php';
    ?>

    <section class="panel">
        <h2 class="visually-hidden">Grant summary</h2>
        <div class="facts facts--wide">
            <?php foreach ($grant['facts'] as $fact): ?>
                <span class="fact">
                    <span class="fact__label"><?= e($fact['label']) ?></span>
                    <span class="fact__value fact__value--lg"><?= e($fact['value']) ?></span>
                </span>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <h2 class="visually-hidden">Moderator vouch</h2>
        <div class="media">
            <span class="avatar avatar--sm"><?= e($vouch['initials']) ?></span>
            <span class="media__body">
                <span class="media__title media__title--sm"><?= e($vouch['line']) ?></span>
                <span class="media__meta"><?= e($vouch['quote']) ?></span>
            </span>
        </div>
        <span class="badge badge--success">
            <span aria-hidden="true">✓</span>
            <?= e($vouch['badge']) ?>
        </span>
    </section>

    <p class="notice notice--info">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        <?= e($grant['notice']) ?>
    </p>

<?php endif; ?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
