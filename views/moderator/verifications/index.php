<?php

declare(strict_types=1);

/**
 * Verifications queue — every membership verification in this moderator's
 * division, filtered by status. The filter pills are links, so filtering is a
 * plain GET and the page works without JavaScript (Rules/CONVENTIONS.md §11).
 *
 * @var array  $filters       pills: label, state, active
 * @var string $filterSummary count line at the end of the filter bar
 * @var array  $verifications rows: name, meta, status, status_label, href
 */

// Sample view data — replaced by the controller once ModerationController lands.
$state = (string) ($_GET['status'] ?? '');

$filters ??= array_map(
    static fn (array $filter): array => $filter + ['active' => $filter['state'] === $state],
    [
        ['label' => 'All',      'state' => ''],
        ['label' => 'Pending',  'state' => 'pending'],
        ['label' => 'Approved', 'state' => 'approved'],
        ['label' => 'Rejected', 'state' => 'rejected'],
    ]
);

$sampleRows = [
    [
        'state'        => 'pending',
        'name'         => 'S. Perera',
        'meta'         => 'NIC + proof of address  ·  Kollupitiya  ·  submitted 2 days ago',
        'status'       => 'warning',
        'status_label' => 'Awaiting review',
        'href'         => '/moderator/verifications/perera-s',
    ],
    [
        'state'        => 'pending',
        'name'         => 'M. Gunawardena',
        'meta'         => 'NIC + utility bill  ·  Kollupitiya  ·  submitted 3 days ago',
        'status'       => 'warning',
        'status_label' => 'Awaiting review',
        'href'         => '/moderator/verifications/gunawardena-m',
    ],
    [
        'state'        => 'pending',
        'name'         => 'A. Nizam',
        'meta'         => 'NIC + GN letter  ·  Kollupitiya  ·  submitted 4 days ago',
        'status'       => 'warning',
        'status_label' => 'Awaiting review',
        'href'         => '/moderator/verifications/nizam-a',
    ],
    [
        'state'        => 'pending',
        'name'         => 'T. Wickrama',
        'meta'         => 'NIC only — address proof missing  ·  submitted 5 days ago',
        'status'       => 'info',
        'status_label' => 'Needs more info',
        'href'         => '/moderator/verifications/wickrama-t',
    ],
    [
        'state'        => 'approved',
        'name'         => 'A. Akalvily',
        'meta'         => 'NIC + proof of address  ·  Wellawatte  ·  approved 14 Jul',
        'status'       => 'success',
        'status_label' => 'Approved',
        'href'         => '/moderator/verifications/akalvily-a',
    ],
    [
        'state'        => 'rejected',
        'name'         => 'D. Rajapaksa',
        'meta'         => 'Address outside this GN division  ·  rejected 11 Jul',
        'status'       => 'error',
        'status_label' => 'Rejected',
        'href'         => '/moderator/verifications/rajapaksa-d',
    ],
];

$verifications ??= array_values(array_filter(
    $sampleRows,
    static fn (array $row): bool => $state === '' || $row['state'] === $state
));

$filterSummary ??= count(array_filter($sampleRows, static fn (array $row): bool => $row['state'] === 'pending'))
    . ' pending';

$pageTitle = 'Verifications';
$navActive = 'verifications';

include __DIR__ . '/../../../partials/header-moderator.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Verifications</h1>
</header>

<div class="filter-bar">
    <ul class="filter-pills">
        <?php foreach ($filters as $filter): ?>
            <li>
                <a class="pill<?= $filter['active'] ? ' pill--active' : '' ?>"
                   href="/moderator/verifications<?= $filter['state'] === '' ? '' : '?status=' . rawurlencode($filter['state']) ?>"
                   <?= $filter['active'] ? 'aria-current="true"' : '' ?>
                ><?= e($filter['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <span class="filter-bar__count"><?= e($filterSummary) ?></span>
</div>

<?php if ($verifications === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon">
            <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-users"></use></svg>
        </span>
        <p class="empty-state__title">Nothing in this queue</p>
        <p class="empty-state__body">
            No verification matches this filter. Try “All” to see every application in your division.
        </p>
        <a class="btn btn--primary" href="/moderator/verifications">Show all verifications</a>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($verifications as $verification): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($verification['name']) ?></span>
                    <span class="list-row__meta"><?= e($verification['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($verification['status']) ?>"><?= e($verification['status_label']) ?></span>
                <a class="btn btn--ghost" href="<?= e($verification['href']) ?>">Review</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
