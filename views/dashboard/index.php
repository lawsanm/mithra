<?php

declare(strict_types=1);

/**
 * Member dashboard. Figma: "Member Dashboard" (45:3) and its
 * "Dashboard — Empty (no bookings)" state (69:23) — the empty state renders
 * when $activeBorrowings is empty.
 *
 * @var array $member            greeting, membership line
 * @var array $stats             four figures for the stat row
 * @var array $activeBorrowings  rows: title, meta, status, status_label, href
 * @var array $listings          cards: title, rate, meta, href
 */

$pageTitle = 'Dashboard';
$navActive = 'dashboard';

include __DIR__ . '/../../partials/header.php';

?>

<header class="page-intro">
    <h1 class="page-intro__title"><?= e($member['greeting']) ?></h1>
    <p class="page-intro__meta"><?= e($member['membership']) ?></p>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <?php $tag = isset($stat['href']) ? 'a' : 'div'; ?>
        <<?= e($tag) ?> class="stat-card"<?= isset($stat['href']) ? ' href="' . e($stat['href']) . '"' : '' ?>>
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value<?= !empty($stat['primary']) ? ' stat-card__value--primary' : '' ?>"><?= e($stat['value']) ?></strong>
            <span class="stat-card__note"><?= e($stat['note']) ?></span>
        </<?= e($tag) ?>>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Your active borrowings</h2>
        <?php if ($activeBorrowings !== []): ?>
            <a class="link section__action" href="/bookings">View all</a>
        <?php endif; ?>
    </div>

    <?php if ($activeBorrowings === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon">
                <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-handshake"></use></svg>
            </span>
            <p class="empty-state__title">No active borrowings</p>
            <p class="empty-state__body">
                When you borrow an item, it appears here with its due date, status and return steps.
            </p>
            <a class="btn btn--primary" href="/items/browse">Browse items near you</a>
        </div>
    <?php else: ?>
        <ul class="row-list">
            <?php foreach ($activeBorrowings as $borrowing): ?>
                <li class="list-row">
                    <span class="thumb thumb--row">Photo</span>
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($borrowing['title']) ?></span>
                        <span class="list-row__meta"><?= e($borrowing['meta']) ?></span>
                    </div>
                    <span class="badge badge--<?= e($borrowing['status']) ?>">
                        <span aria-hidden="true"><?= e($borrowing['status_glyph']) ?></span>
                        <?= e($borrowing['status_label']) ?>
                    </span>
                    <a class="btn btn--ghost" href="<?= e($borrowing['href']) ?>">View</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($listings !== []): ?>
    <section class="section">
        <div class="section__head">
            <h2 class="section__title">Your listings</h2>
            <a class="link section__action" href="/items">Manage items</a>
        </div>

        <ul class="card-grid">
            <?php foreach ($listings as $listing): ?>
                <li class="item-card">
                    <span class="thumb thumb--card thumb--card-tall">Photo</span>
                    <div class="item-card__body">
                        <a class="item-card__title" href="<?= e($listing['href']) ?>"><?= e($listing['title']) ?></a>
                        <span class="item-card__rate"><?= e($listing['rate']) ?></span>
                        <span class="item-card__meta"><?= e($listing['meta']) ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
