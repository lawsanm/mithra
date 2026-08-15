<?php

declare(strict_types=1);

/**
 * My items. Figma: "My Items — List" (70:33) and its "My Items — Empty"
 * state (70:113) — the empty state renders when $items is empty.
 *
 * @var array $filters filter pills: label, slug, active
 * @var array $items   rows: title, meta, status, status_glyph, status_label, href
 */

// Sample view data — replaced by the controller once ItemController lands.
$filters ??= [
    ['label' => 'All (5)',       'slug' => '',          'active' => true],
    ['label' => 'Rentals (4)',   'slug' => 'rentals'],
    ['label' => 'Donations (1)', 'slug' => 'donations'],
];

$items ??= [
    [
        'title'        => 'Rice Cooker (1.8 L)',
        'meta'         => 'Rental  ·  5 pts / day  ·  listed 2 Mar 2026',
        'status'       => 'info',
        'status_glyph' => 'i',
        'status_label' => 'Lent out — due 19 Jul',
        'href'         => base_url() . '/items/1/edit',
    ],
    [
        'title'        => 'Ladder (6 ft)',
        'meta'         => 'Rental  ·  8 pts / day  ·  listed 14 Apr 2026',
        'status'       => 'success',
        'status_glyph' => '✓',
        'status_label' => 'Approved — available',
        'href'         => base_url() . '/items/2/edit',
    ],
    [
        'title'        => 'Pressure Washer',
        'meta'         => 'Rental  ·  20 pts / day  ·  listed yesterday',
        'status'       => 'warning',
        'status_glyph' => '!',
        'status_label' => 'Pending moderator approval',
        'href'         => base_url() . '/items/3/edit',
    ],
    [
        'title'        => 'Baby Clothes Bundle',
        'meta'         => 'Donation  ·  declared 80 pts  ·  3 requests',
        'status'       => 'success',
        'status_glyph' => '✓',
        'status_label' => 'Approved — available',
        'href'         => base_url() . '/items/4/edit',
    ],
    [
        'title'        => 'Badminton Racket Set',
        'meta'         => 'Rental  ·  4 pts / day  ·  listed 20 May 2026',
        'status'       => 'info',
        'status_glyph' => 'i',
        'status_label' => 'Lent out — due 20 Jul',
        'href'         => base_url() . '/items/5/edit',
    ],
];

$pageTitle = 'My items';
$navActive = 'items';

include __DIR__ . '/../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">My Items</h1>
    <?php if ($items !== []): ?>
        <a class="btn btn--primary page-header__action" href="<?= base_url() ?>/items/create">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
            List an item
        </a>
    <?php endif; ?>
</header>

<?php if ($items === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon">
            <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-package"></use></svg>
        </span>
        <p class="empty-state__title">No listings yet</p>
        <p class="empty-state__body">
            List something you rarely use — a drill, a tent, extra chairs — and earn points
            whenever a neighbour borrows it. Every listing is reviewed by your GN division
            moderator first.
        </p>
        <a class="btn btn--primary" href="<?= base_url() ?>/items/create">List your first item</a>
    </div>
<?php else: ?>
    <ul class="filter-pills">
        <?php foreach ($filters as $filter): ?>
            <li>
                <a
                    class="pill<?= !empty($filter['active']) ? ' pill--active' : '' ?>"
                    href="<?= base_url() ?>/items?type=<?= e(rawurlencode($filter['slug'])) ?>"
                    <?= !empty($filter['active']) ? 'aria-current="true"' : '' ?>
                ><?= e($filter['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <ul class="row-list">
        <?php foreach ($items as $listing): ?>
            <li class="list-row">
                <span class="thumb thumb--sm"></span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($listing['title']) ?></span>
                    <span class="list-row__meta"><?= e($listing['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($listing['status']) ?>">
                    <span aria-hidden="true"><?= e($listing['status_glyph']) ?></span>
                    <?= e($listing['status_label']) ?>
                </span>
                <a class="btn btn--ghost" href="<?= e($listing['href']) ?>">Edit</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
