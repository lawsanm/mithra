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

// Sample view data — replaced by the controller once AidGrantController lands.
$grant ??= [
    'reference' => 'Aid grant #A-1042',
    'stage'     => 2,
    'badge'     => ['info', 'i', 'Pending liaison approval'],
    'facts'     => [
        ['label' => 'Purpose',          'value' => 'School supplies'],
        ['label' => 'Amount requested', 'value' => '150 pts'],
        ['label' => 'Requested',        'value' => '10 Jul 2026'],
        ['label' => 'Division',         'value' => 'Kollupitiya'],
    ],
    'notice'    => 'Now with the sponsor liaison. They can approve (and may adjust the amount), '
                 . 'reject with a reason, or ask for more information. You’ll be notified either way.',
];

$vouch ??= [
    'initials' => 'AA',
    'line'     => 'Vouched by Moderator A. Akalvily  ·  12 Jul',
    'quote'    => '“Known family, genuine need for the new school term. No conflict of interest.”',
    'badge'    => 'Vouch complete — no conflict declared',
];

$cooling ??= 'Cooling period: your previous grant closed on 30 Jun 2026. '
           . 'You can request your next aid grant after 14 Aug 2026.';

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

    <ol class="wizard wizard--compact">
        <?php foreach ($stages as $index => $label): ?>
            <?php
            $number    = $index + 1;
            $isDone    = $number < $grant['stage'];
            $isCurrent = $number === $grant['stage'];
            ?>
            <?php if ($number > 1): ?>
                <li aria-hidden="true">
                    <hr class="wizard__connector<?= $number <= $grant['stage'] ? ' wizard__connector--done' : '' ?>">
                </li>
            <?php endif; ?>
            <li class="wizard__step">
                <span class="wizard__marker<?= $isCurrent ? ' wizard__marker--current' : ($isDone ? ' wizard__marker--done' : '') ?>">
                    <?= $isDone ? '✓' : $number ?>
                </span>
                <span class="wizard__label<?= $isCurrent ? ' wizard__label--current' : '' ?>"
                    <?= $isCurrent ? 'aria-current="step"' : '' ?>><?= e($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

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
