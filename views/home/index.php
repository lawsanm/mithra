<?php

declare(strict_types=1);

/**
 * Landing page for a visitor who has not signed in. Figma: Common → "Landing
 * Page" (92:120).
 *
 * The figures are counted from the database, never sample numbers. The design's
 * "estimated savings" figure is left out: nothing in Mithra records one.
 *
 * @var array $stats value, label
 */

$stats = $stats ?? [];

$steps = [
    ['List & lend', 'Photograph an item you rarely use, declare its value, and your division moderator approves it.'],
    ['Borrow with points', 'Request items nearby. Points are held in escrow until the item comes back safely.'],
    ['Give & support', 'Donate items, gift points to neighbours, or request an aid grant when times are tight.'],
];

$pageTitle = 'Lend · Share · Care';
$navActive = '';
$pageClass = 'page--public';

$chrome = 'public';
include __DIR__ . '/../../partials/header.php';

?>

<section class="hero">
    <div class="hero__copy">
        <h1 class="hero__title">Borrow what you need. Lend what you don't.</h1>
        <p class="hero__lede">
            Mithra is a money-free sharing network for Sri Lankan communities. Lend items to
            neighbours in your GN division, earn points, and borrow what you need — with escrow
            protection and trusted local moderators.
        </p>
        <div class="actions">
            <a class="btn btn--primary" href="<?= base_url() ?>/register">Join your community</a>
            <a class="btn btn--ghost" href="<?= base_url() ?>/how-it-works">See how it works</a>
        </div>
        <span class="hero__tag">No money changes hands — only points</span>
    </div>
    <div class="hero__photo" aria-hidden="true"></div>
</section>

<section class="section">
    <h2 class="section-title">How it works</h2>
    <ol class="step-cards">
        <?php foreach ($steps as $index => [$title, $body]): ?>
            <li class="step-card">
                <span class="step-card__number"><?= e((string) ($index + 1)) ?></span>
                <h3 class="step-card__title"><?= e($title) ?></h3>
                <p class="step-card__body"><?= e($body) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<dl class="stat-band">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-band__item">
            <dt class="stat-band__label"><?= e($stat['label']) ?></dt>
            <dd class="stat-band__value"><?= e($stat['value']) ?></dd>
        </div>
    <?php endforeach; ?>
</dl>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
