<?php

declare(strict_types=1);

/**
 * Ratings given and received. Figma: "Ratings — Given / Received" (77:239).
 *
 * @var array      $tabs     direction tabs: label, box, active
 * @var string     $box      given | received
 * @var array      $waiting  records waiting for my rating: name, title, meta, href
 * @var array      $reviews  rows: id, initials, author, rating, text, meta, editable
 * @var array|null $rateForm the dialog's contents, when one is open
 * @var bool       $rateOpen
 * @var array|null $flash
 */

$pageTitle = 'Ratings';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<header class="page-header">
    <h1 class="detail__title">Ratings</h1>
</header>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<?php if ($waiting !== []): ?>
    <section class="panel">
        <h2 class="panel__title">Waiting for your rating</h2>
        <ul class="row-list">
            <?php foreach ($waiting as $row): ?>
                <li class="list-row">
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($row['name']) ?> · <?= e($row['title']) ?></span>
                        <span class="list-row__meta"><?= e($row['meta']) ?></span>
                    </div>
                    <a class="btn btn--primary" href="<?= e($row['href']) ?>">Rate</a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

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
                        <?= e(str_repeat('★ ', $review['rating']) . str_repeat('☆ ', 5 - $review['rating'])) ?>
                    </span>
                    <span class="visually-hidden"><?= e((string) $review['rating']) ?> out of 5</span>
                </div>
                <p class="review__text"><?= e($review['text']) ?></p>
                <span class="review__meta"><?= e($review['meta']) ?></span>
                <?php if ($review['editable']): ?>
                    <div class="actions">
                        <a class="btn btn--ghost" href="<?= base_url() ?>/ratings?box=given&amp;edit=<?= e((string) $review['id']) ?>#rate-review">Edit</a>
                        <form method="post" action="<?= base_url() ?>/ratings/<?= e((string) $review['id']) ?>/delete"
                            data-confirm="Remove your rating? It no longer counts toward their trust score." novalidate>
                            <?= csrf_field() ?>
                            <button class="btn btn--ghost" type="submit">Remove</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($rateForm !== null): ?>
    <?php include __DIR__ . '/../../partials/modal-rate-review.php'; ?>
<?php endif; ?>

<?php
$pageScripts = ['modal.js', 'confirm.js'];
include __DIR__ . '/../../partials/footer.php';
?>
