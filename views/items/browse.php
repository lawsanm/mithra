<?php

declare(strict_types=1);

/**
 * Browse items. Figma: "Browse Items" (63:6) and its "Browse — No results"
 * state (69:69) — the empty state renders when $results is empty.
 *
 * @var string $query       current search term
 * @var string $resultCount subtitle line under the page title
 * @var array  $categories  filter pills: label, slug, active
 * @var array  $results     item cards: title, rate, owner_meta, status,
 *                          status_glyph, status_label, href
 */

// Sample view data — replaced by the controller once ItemController lands.
$query ??= '';

$categories ??= [
    ['label' => 'All',         'slug' => '',            'active' => true],
    ['label' => 'Tools',       'slug' => 'tools'],
    ['label' => 'Electronics', 'slug' => 'electronics'],
    ['label' => 'Kitchen',     'slug' => 'kitchen'],
    ['label' => 'Outdoor',     'slug' => 'outdoor'],
    ['label' => 'Books',       'slug' => 'books'],
    ['label' => 'Baby & Kids', 'slug' => 'baby-kids'],
    ['label' => 'Events',      'slug' => 'events'],
];

$results ??= [
    [
        'title'        => 'Bosch Cordless Drill',
        'rate'         => '15 pts / day',
        'owner_meta'   => 'T.H.K. Madushan  ·  Trust 96  ·  0.4 km',
        'status'       => 'success',
        'status_glyph' => '✓',
        'status_label' => 'Available',
        'href'         => base_url() . '/items/1',
    ],
    [
        'title'        => 'Camping Tent (4-person)',
        'rate'         => '18 pts / day',
        'owner_meta'   => 'J. Kavipriya  ·  Trust 97  ·  1.1 km',
        'status'       => 'success',
        'status_glyph' => '✓',
        'status_label' => 'Available',
        'href'         => base_url() . '/items/2',
    ],
    [
        'title'        => 'Stand Mixer',
        'rate'         => '12 pts / day',
        'owner_meta'   => 'A. Akalvily  ·  Trust 94  ·  0.8 km',
        'status'       => 'warning',
        'status_glyph' => '!',
        'status_label' => 'Back on 19 Jul',
        'href'         => base_url() . '/items/3',
    ],
    [
        'title'        => 'Projector (Full HD)',
        'rate'         => '25 pts / day',
        'owner_meta'   => 'A. Akalvily  ·  Trust 95  ·  1.6 km',
        'status'       => 'success',
        'status_glyph' => '✓',
        'status_label' => 'Available',
        'href'         => base_url() . '/items/4',
    ],
    [
        'title'        => 'Extension Ladder',
        'rate'         => '8 pts / day',
        'owner_meta'   => 'J. Kavipriya  ·  Trust 90  ·  0.3 km',
        'status'       => 'success',
        'status_glyph' => '✓',
        'status_label' => 'Available',
        'href'         => base_url() . '/items/5',
    ],
    [
        'title'        => 'Baby Stroller',
        'rate'         => '10 pts / day',
        'owner_meta'   => 'J. Kavipriya  ·  Trust 98  ·  2.0 km',
        'status'       => 'success',
        'status_glyph' => '✓',
        'status_label' => 'Available',
        'href'         => base_url() . '/items/6',
    ],
    [
        'title'        => 'Sewing Machine',
        'rate'         => '9 pts / day',
        'owner_meta'   => 'T.H.K. Madushan  ·  Trust 88  ·  1.2 km',
        'status'       => 'warning',
        'status_glyph' => '!',
        'status_label' => 'Back on 21 Jul',
        'href'         => base_url() . '/items/7',
    ],
    [
        'title'        => 'Folding Tables ×2',
        'rate'         => '6 pts / day',
        'owner_meta'   => 'A. Akalvily  ·  Trust 96  ·  0.9 km',
        'status'       => 'success',
        'status_glyph' => '✓',
        'status_label' => 'Available',
        'href'         => base_url() . '/items/8',
    ],
];

$resultCount ??= $results === []
    ? '0 results for “' . $query . '” in Kollupitiya'
    : '46 items available in Kollupitiya and nearby GN divisions';

$pageTitle = 'Browse items';
$navActive = 'browse';

include __DIR__ . '/../../partials/header.php';

?>

<header class="page-intro">
    <h1 class="page-intro__title">Browse items near you</h1>
    <p class="page-intro__meta"><?= e($resultCount) ?></p>
</header>

<?php // Search is a read-only GET: no CSRF token, so it never lands in the URL. ?>
<form class="search-bar" method="get" action="<?= base_url() ?>/items/browse" role="search">
    <label class="visually-hidden" for="item-search">Search items</label>
    <input
        class="input input--search"
        type="search"
        id="item-search"
        name="q"
        value="<?= e($query) ?>"
        placeholder="Search drills, tents, cookers…"
    >
    <button class="btn btn--primary" type="submit">Search</button>
</form>

<ul class="filter-pills">
    <?php foreach ($categories as $category): ?>
        <li>
            <a
                class="pill<?= !empty($category['active']) ? ' pill--active' : '' ?>"
                href="<?= base_url() ?>/items/browse?category=<?= e(rawurlencode($category['slug'])) ?>"
                <?= !empty($category['active']) ? 'aria-current="true"' : '' ?>
            ><?= e($category['label']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($results === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon">
            <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-search"></use></svg>
        </span>
        <p class="empty-state__title">No items match “<?= e($query) ?>”</p>
        <p class="empty-state__body">
            Try a broader keyword, another category, or widen your search to nearby GN
            divisions. You can also ask the community to list one.
        </p>
        <a class="btn btn--primary" href="<?= base_url() ?>/items/browse">Clear filters</a>
    </div>
<?php else: ?>
    <ul class="card-grid">
        <?php foreach ($results as $item): ?>
            <li class="item-card">
                <span class="thumb thumb--card">Photo</span>
                <div class="item-card__body">
                    <a class="item-card__title" href="<?= e($item['href']) ?>"><?= e($item['title']) ?></a>
                    <span class="item-card__rate"><?= e($item['rate']) ?></span>
                    <span class="item-card__meta"><?= e($item['owner_meta']) ?></span>
                    <span class="badge badge--<?= e($item['status']) ?>">
                        <span aria-hidden="true"><?= e($item['status_glyph']) ?></span>
                        <?= e($item['status_label']) ?>
                    </span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
