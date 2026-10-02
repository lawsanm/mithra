<?php

declare(strict_types=1);

/**
 * Aid grant status. Figma: "Aid Grant — Status" (76:151) and its
 * "Aid Grant — Empty / Cooling" state (76:227) — the empty state renders when
 * there is no active grant.
 *
 * @var array|null $grant   null when the member has no active grant
 * @var array|null $vouch   moderator vouch shown once complete
 * @var string     $cooling why the member cannot ask now, empty when they can
 * @var bool       $canAsk  show the request button
 * @var array|null $flash
 */

$stages = ['Pending vouch', 'Liaison approval', 'Approved', 'In use', 'Closed'];

$pageTitle = $grant === null ? 'Aid grants' : $grant['reference'];
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

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

    <?php if ($canAsk): ?>
        <div class="actions">
            <a class="btn btn--primary" href="<?= base_url() ?>/aid-grants/create">Request an aid grant</a>
        </div>
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

    <?php if ($grant['details'] !== '' || $grant['photos'] !== []): ?>
        <section class="panel">
            <h2 class="panel__title">Your request</h2>
            <?php if ($grant['details'] !== ''): ?>
                <p class="panel__note"><?= e($grant['details']) ?></p>
            <?php endif; ?>
            <?php if ($grant['photos'] !== []): ?>
                <?php $gridPhotos = $grant['photos']; include __DIR__ . '/../../partials/photo-grid.php'; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($grant['question'] !== ''): ?>
        <section class="panel">
            <h2 class="panel__title">The Sponsor Liaison asked</h2>
            <p class="record-card__quote">“<?= e($grant['question']) ?>”</p>
            <?php if ($grant['answer'] !== ''): ?>
                <p class="panel__note">Your answer: <?= e($grant['answer']) ?></p>
            <?php endif; ?>
            <?php if ($grant['can_reply']): ?>
                <form class="field-row" method="post" action="<?= base_url() ?>/aid-grants/<?= e((string) $grant['id']) ?>/reply" novalidate>
                    <?= csrf_field() ?>
                    <label class="visually-hidden" for="grant-reply">Your answer</label>
                    <input class="input" type="text" id="grant-reply" name="reply" placeholder="Your answer">
                    <button class="btn btn--primary" type="submit">Send answer</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($grant['reason'] !== ''): ?>
        <p class="notice notice--info"><?= e($grant['reason']) ?></p>
    <?php endif; ?>

    <p class="notice notice--info">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        <?= e($grant['notice']) ?>
    </p>

    <?php if ($grant['can_edit'] || $grant['can_withdraw']): ?>
        <div class="actions">
            <?php if ($grant['can_edit']): ?>
                <a class="btn btn--ghost" href="<?= base_url() ?>/aid-grants/<?= e((string) $grant['id']) ?>/edit">Change request</a>
            <?php endif; ?>
            <?php if ($grant['can_withdraw']): ?>
                <form method="post" action="<?= base_url() ?>/aid-grants/<?= e((string) $grant['id']) ?>/withdraw"
                    data-confirm="Withdraw this aid request? You can ask again later." novalidate>
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost" type="submit">Withdraw request</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php
$pageScripts = ['confirm.js'];
include __DIR__ . '/../../partials/footer.php';
?>
