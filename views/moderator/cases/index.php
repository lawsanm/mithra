<?php

declare(strict_types=1);

/**
 * Damage cases queue — every case this moderator is mediating or has closed,
 * filtered by status.
 *
 * @var array  $filters       pills: label, state, active
 * @var string $filterSummary count line at the end of the filter bar
 * @var array  $cases         rows: title, meta, status, status_label, href
 */

// Sample view data — replaced by the controller once ModerationController lands.
$state = (string) ($_GET['status'] ?? '');

$filters ??= array_map(
    static fn (array $filter): array => $filter + ['active' => $filter['state'] === $state],
    [
        ['label' => 'All',       'state' => ''],
        ['label' => 'Open',      'state' => 'open'],
        ['label' => 'Resolved',  'state' => 'resolved'],
        ['label' => 'Escalated', 'state' => 'escalated'],
    ]
);

$sampleRows = [
    [
        'state'        => 'open',
        'title'        => 'Case #1042 — Cordless Drill',
        'meta'         => 'R. Fernando ↔ S. Perera  ·  moderate damage  ·  meet by 28 Jul',
        'status'       => 'warning',
        'status_label' => 'Awaiting meeting',
        'href'         => '/moderator/cases/cordless-drill-case',
    ],
    [
        'state'        => 'open',
        'title'        => 'Case #1039 — Camping Tent',
        'meta'         => 'A. Nizam ↔ M. Gunawardena  ·  minor damage  ·  2 of 3 signed off',
        'status'       => 'info',
        'status_label' => 'Awaiting sign-off',
        'href'         => '/moderator/cases/camping-tent-case',
    ],
    [
        'state'        => 'open',
        'title'        => 'Case #CD-0142 — Grinding Drill',
        'meta'         => 'T.H.K. Madushan ↔ J. Kavipriya  ·  you are a party to this case',
        'status'       => 'warning',
        'status_label' => 'Mediating',
        'href'         => '/moderator/cases/grinding-drill',
    ],
    [
        'state'        => 'resolved',
        'title'        => 'Case #CD-0138 — Camping Tent (4-person)',
        'meta'         => 'M. Lawsan ↔ J. Kavipriya  ·  repair confirmed  ·  closed 12 Jul',
        'status'       => 'success',
        'status_label' => 'Resolved',
        'href'         => '/moderator/cases/camping-tent-4p',
    ],
    [
        'state'        => 'escalated',
        'title'        => 'Case #1035 — Pressure Washer',
        'meta'         => 'K. Bandara ↔ T. Wickrama  ·  party refused to sign off',
        'status'       => 'error',
        'status_label' => 'Escalated to Admin',
        'href'         => '/moderator/cases/pressure-washer-case',
    ],
];

$cases ??= array_values(array_filter(
    $sampleRows,
    static fn (array $row): bool => $state === '' || $row['state'] === $state
));

$filterSummary ??= count(array_filter($sampleRows, static fn (array $row): bool => $row['state'] === 'open'))
    . ' open';

$pageTitle = 'Damage cases';
$navActive = 'cases';

include __DIR__ . '/../../../partials/header-moderator.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Damage cases</h1>
</header>

<div class="filter-bar">
    <ul class="filter-pills">
        <?php foreach ($filters as $filter): ?>
            <li>
                <a class="pill<?= $filter['active'] ? ' pill--active' : '' ?>"
                   href="/moderator/cases<?= $filter['state'] === '' ? '' : '?status=' . rawurlencode($filter['state']) ?>"
                   <?= $filter['active'] ? 'aria-current="true"' : '' ?>
                ><?= e($filter['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <span class="filter-bar__count"><?= e($filterSummary) ?></span>
</div>

<?php if ($cases === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon">
            <svg class="icon icon--lg" aria-hidden="true"><use href="#icon-handshake"></use></svg>
        </span>
        <p class="empty-state__title">Nothing in this queue</p>
        <p class="empty-state__body">
            No case matches this filter. Try “All” to see every damage case in your division.
        </p>
        <a class="btn btn--primary" href="/moderator/cases">Show all cases</a>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($cases as $case): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($case['title']) ?></span>
                    <span class="list-row__meta"><?= e($case['meta']) ?></span>
                </div>
                <span class="badge badge--<?= e($case['status']) ?>"><?= e($case['status_label']) ?></span>
                <a class="btn btn--ghost" href="<?= e($case['href']) ?>">Open</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
