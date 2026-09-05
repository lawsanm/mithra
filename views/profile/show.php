<?php

declare(strict_types=1);

/**
 * Public member profile. Figma: "Public Member Profile" (94:279).
 *
 * @var array $member  initials, name, verified, meta, score, score_note
 * @var array $stats   four headline figures
 * @var array $reviews recent ratings received
 */

$pageTitle = $member['name'];
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<section class="panel">
    <h1 class="visually-hidden"><?= e($member['name']) ?></h1>
    <div class="profile-head">
        <span class="avatar avatar--xl"><?= e($member['initials']) ?></span>
        <div class="profile-head__body">
            <div class="profile-head__name-row">
                <span class="profile-head__name"><?= e($member['name']) ?></span>
                <?php if ($member['verified']): ?>
                    <span class="badge badge--success">
                        <span aria-hidden="true">✓</span>
                        Verified
                    </span>
                <?php endif; ?>
            </div>
            <span class="profile-head__meta"><?= e($member['meta']) ?></span>
        </div>
        <span class="profile-head__score">
            <strong><?= e($member['score']) ?></strong>
            <span><?= e($member['score_note']) ?></span>
        </span>
    </div>
</section>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-card stat-card--compact">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value"><?= e($stat['value']) ?></strong>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="section-heading">Recent ratings</h2>

<?php if ($reviews === []): ?>
    <p class="empty-state__body">No ratings yet.</p>
<?php endif; ?>

<ul class="row-list">
    <?php foreach ($reviews as $review): ?>
        <li class="review">
            <span class="avatar avatar--sm"><?= e($review['initials']) ?></span>
            <div class="review__body">
                <div class="review__head">
                    <span class="review__author"><?= e($review['author']) ?></span>
                    <span class="review__stars" aria-hidden="true">
                        <?= str_repeat('★ ', $review['rating']) . str_repeat('☆ ', 5 - $review['rating']) ?>
                    </span>
                    <span class="visually-hidden"><?= e((string) $review['rating']) ?> out of 5</span>
                </div>
                <p class="review__text"><?= e($review['text']) ?></p>
                <span class="review__meta"><?= e($review['meta']) ?></span>
            </div>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
