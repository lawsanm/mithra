<?php

declare(strict_types=1);

/**
 * Browse items. Figma: "Browse Items" (63:6) and its "Browse — No results"
 * state (69:69) — the empty state renders when $results is empty.
 *
 * @var string $query       current search term
 * @var string $resultCount subtitle line under the page title
 * @var array  $categories  filter pills: label, slug, active
 * @var array  $results     item cards: title, rate, photo, owner_meta, status,
 *                          status_glyph, status_label, href
 * @var int    $page        1-based page number
 * @var bool   $hasNextPage
 * @var string $community   'home' or 'temporary' — which division is shown
 * @var array  $communities home and temporary names; empty without a temporary community
 * @var array|null $flash
 */

$query       = $query ?? '';
$typeSlug    = $typeSlug ?? '';
$categories  = $categories ?? [];
$results     = $results ?? [];
$resultCount = $resultCount ?? '';
$page        = $page ?? 1;
$hasNextPage = $hasNextPage ?? false;
$community   = $community ?? 'home';
$communities = $communities ?? [];

$activeSlug = '';

foreach ($categories as $category) {
    if (!empty($category['active'])) {
        $activeSlug = (string) $category['slug'];
    }
}

$browseUrl = static function (array $changes = []) use ($query, $activeSlug, $typeSlug, $community): string {
    return base_url() . '/items/browse?' . http_build_query(array_filter(array_replace([
        'q'         => $query,
        'category'  => $activeSlug,
        'type'      => $typeSlug,
        'community' => $community === 'temporary' ? 'temporary' : '',
    ], $changes), static fn ($value): bool => $value !== ''));
};

$pageTitle = 'Browse items';
$navActive = 'browse';

include __DIR__ . '/../../partials/header.php';

?>

<header class="page-intro">
    <h1 class="page-intro__title">Browse items near you</h1>
    <p class="page-intro__meta"><?= e($resultCount) ?></p>
</header>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<?php if ($communities !== []): ?>
    <ul class="filter-pills" aria-label="Community">
        <?php foreach (['' => 'Home · ' . $communities['home'], 'temporary' => 'Temporary · ' . $communities['temporary']] as $slug => $label): ?>
            <?php $isActive = ($slug === 'temporary') === ($community === 'temporary'); ?>
            <li>
                <a class="pill<?= $isActive ? ' pill--active' : '' ?>"
                    href="<?= e($browseUrl(['community' => $slug, 'page' => ''])) ?>"
                    <?= $isActive ? 'aria-current="true"' : '' ?>><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<ul class="filter-pills" aria-label="Listing type">
    <?php foreach (['' => 'All items', 'rentals' => 'Rentals', 'donations' => 'Donations'] as $slug => $label): ?>
        <li>
            <a class="pill<?= $typeSlug === $slug ? ' pill--active' : '' ?>"
                href="<?= e($browseUrl(['type' => $slug])) ?>"
                <?= $typeSlug === $slug ? 'aria-current="true"' : '' ?>><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php // Search is a read-only GET: no CSRF token, so it never lands in the URL. ?>
<form class="search-bar" method="get" action="<?= base_url() ?>/items/browse" role="search" novalidate>
    <label class="visually-hidden" for="item-search">Search items</label>
    <input
        class="input input--search"
        type="search"
        id="item-search"
        name="q"
        value="<?= e($query) ?>"
        placeholder="Search drills, tents, cookers…"
    >
    <input type="hidden" name="category" value="<?= e($activeSlug) ?>">
    <input type="hidden" name="type" value="<?= e($typeSlug) ?>">
    <?php if ($community === 'temporary'): ?>
        <input type="hidden" name="community" value="temporary">
    <?php endif; ?>
    <button class="btn btn--primary" type="submit">Search</button>
</form>

<ul class="filter-pills" aria-label="Item category">
    <?php foreach ($categories as $category): ?>
        <li>
            <a
                class="pill<?= !empty($category['active']) ? ' pill--active' : '' ?>"
                href="<?= e($browseUrl(['category' => $category['slug']])) ?>"
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
        <?php if ($query === ''): ?>
            <p class="empty-state__title">No items match these filters</p>
            <p class="empty-state__body">
                Try another category or listing type, or list something for your neighbours to borrow or receive as a donation.
            </p>
            <a class="btn btn--primary" href="<?= base_url() ?>/items/create">List an item</a>
        <?php else: ?>
            <p class="empty-state__title">No items match “<?= e($query) ?>”</p>
            <p class="empty-state__body">
                Try a broader keyword or another category. You can also ask the community to
                list one.
            </p>
            <a class="btn btn--primary" href="<?= base_url() ?>/items/browse">Clear filters</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <ul class="card-grid">
        <?php foreach ($results as $card): ?>
            <li class="item-card">
                <?php if ($card['photo'] !== null): ?>
                    <img class="thumb thumb--card thumb__img thumb--item" src="<?= e($card['photo']) ?>" alt="<?= e($card['title']) ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <span class="thumb thumb--card">No photo</span>
                <?php endif; ?>
                <div class="item-card__body">
                    <a class="item-card__title" href="<?= e($card['href']) ?>"><?= e($card['title']) ?></a>
                    <span class="item-card__rate"><?= e($card['rate']) ?></span>
                    <span class="item-card__meta"><?= e($card['owner_meta']) ?></span>
                    <span class="badge badge--<?= e($card['status']) ?>">
                        <span aria-hidden="true"><?= e($card['status_glyph']) ?></span>
                        <?= e($card['status_label']) ?>
                    </span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($page > 1 || $hasNextPage): ?>
        <div class="actions">
            <?php if ($page > 1): ?>
                <a class="btn btn--ghost" href="<?= e($browseUrl(['page' => $page - 1])) ?>">Previous</a>
            <?php endif; ?>
            <?php if ($hasNextPage): ?>
                <a class="btn btn--ghost" href="<?= e($browseUrl(['page' => $page + 1])) ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
