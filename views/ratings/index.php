<?php

declare(strict_types=1);

/**
 * Ratings given and received. Figma: "Ratings — Given / Received" (77:239).
 *
 * @var array $tabs    direction tabs: label, box, active
 * @var array $reviews rows: initials, author, stars, text, meta
 */

// Sample view data — replaced by the controller once RatingController lands.
$tabs ??= [
    ['label' => 'Received (23)', 'box' => 'received', 'active' => true],
    ['label' => 'Given (21)',    'box' => 'given'],
];

$reviews ??= [
    [
        'initials' => 'TM',
        'author'   => 'T.H.K. Madushan',
        'rating'   => 5,
        'text'     => '“Returned the drill spotless and on time. Would lend again without hesitation.”',
        'meta'     => '17 Jul 2026  ·  Bosch Cordless Drill',
    ],
    [
        'initials' => 'JK',
        'author'   => 'J. Kavipriya',
        'rating'   => 5,
        'text'     => '“Careful with the tent, great communication about pickup.”',
        'meta'     => '2 Jul 2026  ·  Camping Tent',
    ],
    [
        'initials' => 'AA',
        'author'   => 'A. Akalvily',
        'rating'   => 4,
        'text'     => '“All good — slightly late confirming the return window.”',
        'meta'     => '18 Jun 2026  ·  Stand Mixer',
    ],
    [
        'initials' => 'AA',
        'author'   => 'A. Akalvily',
        'rating'   => 5,
        'text'     => '“Textbook borrower. On time, item as handed over.”',
        'meta'     => '30 May 2026  ·  Projector',
    ],
];

$pageTitle = 'Ratings';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="detail__title">Ratings</h1>

<nav class="tabs" aria-label="Rating direction">
    <?php foreach ($tabs as $tab): ?>
        <a
            class="tabs__link<?= !empty($tab['active']) ? ' tabs__link--active' : '' ?>"
            href="/ratings?box=<?= e(rawurlencode($tab['box'])) ?>"
            <?= !empty($tab['active']) ? 'aria-current="page"' : '' ?>
        ><?= e($tab['label']) ?></a>
    <?php endforeach; ?>
</nav>

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
