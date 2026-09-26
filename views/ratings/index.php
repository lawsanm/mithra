<?php

declare(strict_types=1);

/**
 * Ratings given and received. Figma: "Ratings — Given / Received" (77:239).
 *
 * @var array $tabs    direction tabs: label, box, active
 * @var array $reviews rows: initials, author, stars, text, meta
 */

$pageTitle = 'Ratings';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<header class="page-header">
    <h1 class="detail__title">Ratings</h1>
    <button class="btn btn--ghost" type="button" disabled>Rating submission unavailable</button>
</header>

<nav class="tabs" aria-label="Rating direction">
    <?php foreach ($tabs as $tab): ?>
        <a
            class="tabs__link<?= !empty($tab['active']) ? ' tabs__link--active' : '' ?>"
            href="<?= base_url() ?>/ratings?box=<?= e(rawurlencode($tab['box'])) ?>"
            <?= !empty($tab['active']) ? 'aria-current="page"' : '' ?>
        ><?= e($tab['label']) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($reviews === []): ?>
    <p class="empty-state">No ratings to show.</p>
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



<?php
$pageScripts = ['modal.js'];
include __DIR__ . '/../../partials/footer.php';
?>
