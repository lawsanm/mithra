<?php

declare(strict_types=1);

/**
 * Sponsors — List. Figma "Sponsors — List" (377:208).
 *
 * @var array  $sponsors    rows: id, name, contact, points, agreement_label, badge, badge_label, active
 * @var string $search      current search term
 * @var string $sort        current sort key
 * @var string $status      current agreement-status filter
 * @var array  $agreementStatuses value => label
 * @var int    $page        current page
 * @var bool   $hasNextPage whether another page follows
 */

$sponsors    = $sponsors ?? [];
$search      = $search ?? '';
$sort        = $sort ?? '';
$status      = $status ?? '';
$page        = $page ?? 1;
$hasNextPage = $hasNextPage ?? false;

$pageQuery = static function (int $target) use ($search, $sort, $status): string {
    return base_url() . '/sponsor-liaison/sponsors?' . http_build_query(array_filter([
        'q'      => $search,
        'sort'   => $sort,
        'status' => $status,
        'page'   => $target > 1 ? $target : null,
    ]));
};

$sortOptions = [
    ''             => 'Sort: contribution',
    'name'         => 'Sort: name',
    'recently_added' => 'Sort: recently added',
];

$statusOptions = ['' => 'All statuses'] + ($agreementStatuses ?? []);

$pageTitle = 'Sponsors';
$navActive = 'sponsors';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Sponsors</h1>
    <a class="btn btn--primary page-header__action" href="<?= base_url() ?>/sponsor-liaison/sponsors/onboarding">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
        Add sponsor
    </a>
</header>

<form class="field-row" method="get" action="<?= base_url() ?>/sponsor-liaison/sponsors" novalidate>
    <div class="field">
        <input class="input input--search" aria-label="Search sponsors" type="search" name="q" placeholder="Search sponsors" value="<?= e($search) ?>">
    </div>
    <div class="field">
        <select class="input" aria-label="Sort sponsors" name="sort" data-auto-submit>
            <?php foreach ($sortOptions as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <select class="input" aria-label="Agreement status" name="status" data-auto-submit>
            <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn--ghost" type="submit">Apply filters</button>
</form>

<?php if ($sponsors === []): ?>
    <div class="empty-state">
        <p class="empty-state__title">No sponsors match</p>
        <p class="empty-state__body">Try a different search or clear the filters.</p>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($sponsors as $sponsor): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title">
                        <?= e($sponsor['name']) ?>
                        <?php if (!$sponsor['active']): ?>
                            <span class="badge badge--<?= e($sponsor['badge']) ?>"><?= e($sponsor['badge_label']) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="list-row__meta"><?= e($sponsor['contact']) ?>  ·  <?= e($sponsor['agreement_label']) ?></span>
                </div>
                <strong class="list-row__amount"><?= e($sponsor['points']) ?></strong>
                <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/sponsors/<?= e((string) $sponsor['id']) ?>">View</a>
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

<?php $pageScripts = ['filter-select.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
