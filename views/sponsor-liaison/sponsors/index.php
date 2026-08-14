<?php

declare(strict_types=1);

/**
 * Sponsors — List. Figma "Sponsors — List" (377:208).
 *
 * @var array  $sponsors rows: id, name, email, points, status
 * @var string $search   current search term
 * @var string $sort     current sort key
 * @var string $status   current status filter
 */

// Sample view data — replaced by the controller once SponsorLiaisonController lands.
$sponsors ??= [
    ['id' => 1, 'name' => 'Northwind Co', 'email' => 'contact@northwind.lk', 'points' => '16,000 pts'],
    ['id' => 2, 'name' => 'ACM Corp',     'email' => 'hello@acm.lk',         'points' => '6,500 pts'],
    ['id' => 3, 'name' => 'Texa',         'email' => 'team@texa.lk',         'points' => '7,500 pts'],
    ['id' => 4, 'name' => 'MNM',          'email' => 'contact@mnm.lk',       'points' => '7,000 pts'],
];

$search ??= '';
$sort   ??= '';
$status ??= '';

$sortOptions = [
    ''             => 'Sort: contribution',
    'name'         => 'Sort: name',
    'recently_added' => 'Sort: recently added',
];

$statusOptions = [
    ''        => 'All statuses',
    'signed'  => 'Signed agreement on file',
    'pending' => 'Pending signature',
    'verbal'  => 'Verbal agreement only',
];

$pageTitle = 'Sponsors';
$navActive = 'sponsors';

include __DIR__ . '/../../../partials/header-sponsor-liaison.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Sponsors</h1>
    <a class="btn btn--primary page-header__action" href="/sponsor-liaison/sponsors/onboarding">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
        Add sponsor
    </a>
</header>

<form class="field-row" method="get" action="/sponsor-liaison/sponsors">
    <div class="field">
        <input class="input input--search" type="search" name="q" placeholder="Search sponsors" value="<?= e($search) ?>">
    </div>
    <div class="field">
        <select class="input" name="sort" data-auto-submit>
            <?php foreach ($sortOptions as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <select class="input" name="status" data-auto-submit>
            <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
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
                    <span class="list-row__title"><?= e($sponsor['name']) ?></span>
                    <span class="list-row__meta"><?= e($sponsor['email']) ?></span>
                </div>
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 2px;">
                    <span class="list-row__title"><?= e($sponsor['points']) ?></span>
                    <span class="stat-card__note">injected</span>
                </div>
                <a class="btn btn--ghost" href="/sponsor-liaison/sponsors/<?= e((string) $sponsor['id']) ?>">View</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php $pageScripts = ['filter-select.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
