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
 * @var array|null $flash
 */

$query       = $query ?? '';
$categories  = $categories ?? [];
$results     = $results ?? [];
$resultCount = $resultCount ?? '';
$page        = $page ?? 1;
$hasNextPage = $hasNextPage ?? false;

$activeSlug = '';

foreach ($categories as $category) {
    if (!empty($category['active'])) {
        $activeSlug = (string) $category['slug'];
    }
}

$pageQuery = static function (int $target) use ($query, $activeSlug): string {
    return base_url() . '/items/browse?' . http_build_query(array_filter([
        'q'        => $query,
        'category' => $activeSlug,
        'page'     => $target > 1 ? $target : '',
    ]));
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
    <input type="hidden" name="category" value="<?= e($activeSlug) ?>">
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
        <?php if ($query === ''): ?>
            <p class="empty-state__title">Nothing listed here yet</p>
            <p class="empty-state__body">
                No approved listings in your community right now. Be the first — list something
                you rarely use and your neighbours can borrow it.
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
                    <img class="thumb thumb--card thumb__img" src="<?= e($card['photo']) ?>" alt="">
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
                <a class="btn btn--ghost" href="<?= e($pageQuery($page - 1)) ?>">Previous</a>
            <?php endif; ?>
            <?php if ($hasNextPage): ?>
                <a class="btn btn--ghost" href="<?= e($pageQuery($page + 1)) ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
