<?php

declare(strict_types=1);

/**
 * Listing approvals queue — item listings waiting on a moderator's sign-off
 * before they go live, filtered by status.
 *
 * @var array  $filters       pills: label, state, active
 * @var string $filterSummary count line at the end of the filter bar
 * @var array  $listings      rows: title, meta, status, status_label, href
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
        'title'        => 'Cordless Drill — Bosch 18V',
        'meta'         => 'R. Fernando  ·  declared 12,000 LKR  ·  proof: receipt  ·  1 day ago',
        'status'       => 'warning',
        'status_label' => 'Awaiting review',
        'href'         => base_url() . '/moderator/listing-approvals/cordless-drill',
    ],
    [
        'state'        => 'pending',
        'title'        => 'Folding Table (6ft)',
        'meta'         => 'N. Silva  ·  declared 8,500 LKR  ·  proof: photo  ·  2 days ago',
        'status'       => 'warning',
        'status_label' => 'Awaiting review',
        'href'         => base_url() . '/moderator/listing-approvals/folding-table',
    ],
    [
        'state'        => 'pending',
        'title'        => 'Pressure Washer',
        'meta'         => 'K. Bandara  ·  declared 22,000 LKR  ·  inspection requested  ·  2 days ago',
        'status'       => 'info',
        'status_label' => 'Inspection requested',
        'href'         => base_url() . '/moderator/listing-approvals/pressure-washer-kb',
    ],
    [
        'state'        => 'pending',
        'title'        => 'Sewing Machine — Singer',
        'meta'         => 'P. Mendis  ·  declared 15,000 LKR  ·  proof: receipt  ·  3 days ago',
        'status'       => 'warning',
        'status_label' => 'Awaiting review',
        'href'         => base_url() . '/moderator/listing-approvals/sewing-machine',
    ],
    [
        'state'        => 'approved',
        'title'        => 'Pressure Washer',
        'meta'         => 'T.H.K. Madushan  ·  declared 32,000 LKR  ·  approved 15 Jul',
        'status'       => 'success',
        'status_label' => 'Approved',
        'href'         => base_url() . '/moderator/listing-approvals/pressure-washer',
    ],
    [
        'state'        => 'rejected',
        'title'        => 'Petrol Generator',
        'meta'         => 'S. Perera  ·  fuel-powered items are not lendable  ·  rejected 12 Jul',
        'status'       => 'error',
        'status_label' => 'Rejected',
        'href'         => base_url() . '/moderator/listing-approvals/petrol-generator',
    ],
];

$listings ??= array_values(array_filter(
    $sampleRows,
    static fn (array $row): bool => $state === '' || $row['state'] === $state
));

$filterSummary ??= count(array_filter($sampleRows, static fn (array $row): bool => $row['state'] === 'pending'))
    . ' pending';

$pageTitle = 'Listing approvals';
$navActive = 'listing-approvals';

include __DIR__ . '/../../../partials/header-moderator.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Listing approvals</h1>
</header>

<div class="filter-bar">
    <ul class="filter-pills">
        <?php foreach ($filters as $filter): ?>
            <li>
                <a class="pill<?= $filter['active'] ? ' pill--active' : '' ?>"
                   href="<?= base_url() ?>/moderator/listing-approvals<?= $filter['state'] === '' ? '' : '?status=' . rawurlencode($filter['state']) ?>"
                   <?= $filter['active'] ? 'aria-current="true"' : '' ?>
                ><?= e($filter['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <span class="filter-bar__count"><?= e($filterSummary) ?></span>
</div>

<?php if ($listings === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon">
            <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-package"></use></svg>
        </span>
        <p class="empty-state__title">Nothing in this queue</p>
        <p class="empty-state__body">
            No listing matches this filter. Try “All” to see every listing awaiting approval in your division.
        </p>
        <a class="btn btn--primary" href="<?= base_url() ?>/moderator/listing-approvals">Show all listings</a>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($listings as $listing): ?>
            <li class="list-row">
                <span class="thumb thumb--sm">Photo</span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($listing['title']) ?></span>
                    <span class="list-row__meta"><?= e($listing['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($listing['status']) ?>"><?= e($listing['status_label']) ?></span>
                <a class="btn btn--ghost" href="<?= e($listing['href']) ?>">Review</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
