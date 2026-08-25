<?php

declare(strict_types=1);

/**
 * My items. Figma: "My Items — List" (70:33) and its "My Items — Empty"
 * state (70:113) — the empty state renders when $items is empty.
 *
 * @var array      $filters filter pills: label, slug, active
 * @var array      $items   rows: id, title, meta, photo, status, status_glyph,
 *                          status_label, href, edit_href
 * @var array|null $flash
 */

$filters = $filters ?? [];
$items   = $items ?? [];

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

<?php include __DIR__ . '/../../partials/flash.php'; ?>

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
                <?php if ($listing['photo'] !== null): ?>
                    <img class="thumb thumb--sm thumb__img" src="<?= e($listing['photo']) ?>" alt="">
                <?php else: ?>
                    <span class="thumb thumb--sm"></span>
                <?php endif; ?>
                <div class="list-row__body">
                    <a class="list-row__title" href="<?= e($listing['href']) ?>"><?= e($listing['title']) ?></a>
                    <span class="list-row__meta"><?= e($listing['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($listing['status']) ?>">
                    <span aria-hidden="true"><?= e($listing['status_glyph']) ?></span>
                    <?= e($listing['status_label']) ?>
                </span>
                <a class="btn btn--ghost" href="<?= e($listing['edit_href']) ?>">Edit</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
