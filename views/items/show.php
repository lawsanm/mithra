<?php

declare(strict_types=1);

/**
 * Item detail. Figma: "Item Detail" (65:16).
 *
 * @var array $item      title, rate, declared_value, description, photo count,
 *                       status, status_glyph, status_label, category
 * @var array $owner     initials, name, verified, meta, href
 * @var array $quote     from, to, days_label, total
 */

// Sample view data — replaced by the controller once ItemController lands.
$item ??= [
    'title'          => 'Bosch Cordless Drill GSB 120',
    'category'       => 'Tools',
    'category_slug'  => 'tools',
    'rate'           => '15 pts / day',
    'declared_value' => 'Declared value: 300 pts',
    'description'    => 'Lightly used cordless drill with two batteries, charger and a '
                      . '20-piece bit set. Great for shelves, curtain rails and light '
                      . 'masonry. Please return with both batteries charged.',
    'photo_count'    => 4,
    'status'         => 'success',
    'status_glyph'   => '✓',
    'status_label'   => 'Available now',
];

$owner ??= [
    'initials' => 'TM',
    'name'     => 'T.H.K. Madushan',
    'verified' => true,
    'meta'     => 'Trust score 96 / 100  ·  23 successful lends  ·  Member since 2024  ·  0.4 km away',
    'href'     => base_url() . '/members/1',
];

$quote ??= [
    'from'       => '',
    'to'         => '',
    'days_label' => '5 days  ·  Total',
    'total'      => '75 pts',
];

$pageTitle = $item['title'];
$navActive = 'browse';

include __DIR__ . '/../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/items/browse">Browse Items</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <a class="breadcrumb__link" href="<?= base_url() ?>/items/browse?category=<?= e(rawurlencode($item['category_slug'])) ?>"><?= e($item['category']) ?></a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($item['title']) ?></span>
</nav>

<div class="detail">
    <div class="detail__aside">
        <span class="thumb gallery__main">Main photo</span>
        <div class="gallery__thumbs">
            <?php for ($photo = 1; $photo <= $item['photo_count']; $photo++): ?>
                <span class="thumb gallery__thumb<?= $photo === 1 ? ' gallery__thumb--current' : '' ?>">Photo</span>
            <?php endfor; ?>
        </div>
    </div>

    <div class="detail__main">
        <span class="badge badge--<?= e($item['status']) ?>">
            <span aria-hidden="true"><?= e($item['status_glyph']) ?></span>
            <?= e($item['status_label']) ?>
        </span>

        <h1 class="detail__title"><?= e($item['title']) ?></h1>

        <p class="price-row">
            <span class="price"><?= e($item['rate']) ?></span>
            <span class="price__note"><?= e($item['declared_value']) ?></span>
        </p>

        <p class="detail__prose"><?= e($item['description']) ?></p>

        <hr class="divider detail__divider">

        <div class="owner-card">
            <span class="avatar avatar--lg"><?= e($owner['initials']) ?></span>
            <div class="owner-card__body">
                <span class="owner-card__name-row">
                    <span class="owner-card__name"><?= e($owner['name']) ?></span>
                    <?php if ($owner['verified']): ?>
                        <span class="verified-pill">✓ Verified</span>
                    <?php endif; ?>
                </span>
                <span class="owner-card__meta"><?= e($owner['meta']) ?></span>
            </div>
            <a class="btn btn--ghost" href="<?= e($owner['href']) ?>">View profile</a>
        </div>

        <form class="stack" method="post" action="<?= base_url() ?>/bookings">
            <?= csrf_field() ?>

            <div class="field-row">
                <div class="field">
                    <label class="visually-hidden" for="borrow-from">From date</label>
                    <input
                        class="input input--date"
                        type="date"
                        id="borrow-from"
                        name="from_date"
                        value="<?= e($quote['from']) ?>"
                        placeholder="From date"
                        required
                    >
                </div>
                <div class="field">
                    <label class="visually-hidden" for="borrow-to">To date</label>
                    <input
                        class="input input--date"
                        type="date"
                        id="borrow-to"
                        name="to_date"
                        value="<?= e($quote['to']) ?>"
                        placeholder="To date"
                        required
                    >
                </div>
            </div>

            <p class="total-row">
                <span class="total-row__label"><?= e($quote['days_label']) ?></span>
                <strong class="total-row__value"><?= e($quote['total']) ?></strong>
            </p>

            <div class="actions">
                <?php // Without JS this submits straight through; with it, the modal collects the details. ?>
                <button class="btn btn--primary" type="submit" data-modal-open="request-borrow">Request to Borrow</button>
                <a class="btn btn--ghost" href="<?= base_url() ?>/messages/new?member=<?= e(rawurlencode($owner['name'])) ?>">Message lender</a>
            </div>
        </form>

        <p class="notice notice--info">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            Condition photos are required at handover and at return. Your points are held
            in escrow until the lender confirms the item is back in good condition.
        </p>
    </div>
</div>

<?php include __DIR__ . '/../../partials/modal-request-borrow.php'; ?>

<?php
$pageScripts = ['modal.js'];
include __DIR__ . '/../../partials/footer.php';
?>
