<?php

declare(strict_types=1);

/**
 * Aid vouching queue — disaster relief aid requests waiting on this moderator's
 * vouch. Vouching is a state change, so each row posts its own form; the
 * service behind it belongs to the aid grant module.
 *
 * @var array  $filters       pills: label, state, active
 * @var string $filterSummary count line at the end of the filter bar
 * @var array  $requests      rows: id, title, meta, status, status_label, vouchable
 */

// Sample view data — replaced by the controller once AidGrantController lands.
$state = (string) ($_GET['status'] ?? '');

$filters ??= array_map(
    static fn (array $filter): array => $filter + ['active' => $filter['state'] === $state],
    [
        ['label' => 'All',            'state' => ''],
        ['label' => 'Awaiting vouch', 'state' => 'awaiting'],
        ['label' => 'Vouched',        'state' => 'vouched'],
        ['label' => 'Rejected',       'state' => 'rejected'],
    ]
);

$sampleRows = [
    [
        'state'        => 'awaiting',
        'id'           => '1',
        'title'        => 'D. Kumari — 300 pts',
        'meta'         => 'School supplies  ·  household of 4  ·  requested 2 days ago',
        'status'       => 'warning',
        'status_label' => 'Awaiting vouch',
        'vouchable'    => true,
    ],
    [
        'state'        => 'awaiting',
        'id'           => '2',
        'title'        => 'H. Perera — 500 pts',
        'meta'         => 'Medical transport  ·  requested 4 days ago',
        'status'       => 'warning',
        'status_label' => 'Awaiting vouch',
        'vouchable'    => true,
    ],
    [
        'state'        => 'vouched',
        'id'           => '3',
        'title'        => 'A. Akalvily — 250 pts',
        'meta'         => 'Dry rations and drinking water  ·  vouched 15 Jul  ·  with Admin',
        'status'       => 'success',
        'status_label' => 'Vouched',
        'vouchable'    => false,
    ],
    [
        'state'        => 'rejected',
        'id'           => '4',
        'title'        => 'B. Silva — 400 pts',
        'meta'         => 'Outside this GN division  ·  rejected 13 Jul',
        'status'       => 'error',
        'status_label' => 'Rejected',
        'vouchable'    => false,
    ],
];

$requests ??= array_values(array_filter(
    $sampleRows,
    static fn (array $row): bool => $state === '' || $row['state'] === $state
));

$filterSummary ??= count(array_filter($sampleRows, static fn (array $row): bool => $row['state'] === 'awaiting'))
    . ' awaiting vouch';

$pageTitle = 'Aid vouching';
$navActive = 'disasters';

include __DIR__ . '/../../../partials/header-moderator.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link link" href="/moderator/disasters">Disasters</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Aid vouching</span>
</nav>

<header class="page-header">
    <h1 class="page-header__title">Aid vouching</h1>
</header>

<div class="filter-bar">
    <ul class="filter-pills">
        <?php foreach ($filters as $filter): ?>
            <li>
                <a class="pill<?= $filter['active'] ? ' pill--active' : '' ?>"
                   href="/moderator/aid-vouching<?= $filter['state'] === '' ? '' : '?status=' . rawurlencode($filter['state']) ?>"
                   <?= $filter['active'] ? 'aria-current="true"' : '' ?>
                ><?= e($filter['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <span class="filter-bar__count"><?= e($filterSummary) ?></span>
</div>

<?php if ($requests === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon">
            <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-heart"></use></svg>
        </span>
        <p class="empty-state__title">Nothing in this queue</p>
        <p class="empty-state__body">
            No aid request matches this filter. Try “All” to see every request in your division.
        </p>
        <a class="btn btn--primary" href="/moderator/aid-vouching">Show all requests</a>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($requests as $request): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($request['title']) ?></span>
                    <span class="list-row__meta"><?= e($request['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($request['status']) ?>"><?= e($request['status_label']) ?></span>
                <a class="btn btn--ghost" href="/aid-grants/<?= rawurlencode($request['id']) ?>">View request</a>
                <?php if ($request['vouchable']): ?>
                    <form method="post" action="/moderator/aid-vouching/<?= rawurlencode($request['id']) ?>/vouch">
                        <?= csrf_field() ?>
                        <button class="btn btn--primary" type="submit">Vouch</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
