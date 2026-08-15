<?php

declare(strict_types=1);

/**
 * Global ledger — admin append-only transaction log with filters.
 *
 * @var array  $filters   label, slug, active(bool)
 * @var array  $entries   ref, date, title, meta, amount, amount_class
 * @var string $search
 * @var string $dateRange
 * @var string $division
 */

$filters ??= [
    ['label' => 'All types',  'slug' => '',          'active' => true],
    ['label' => 'Escrow',     'slug' => 'escrow'],
    ['label' => 'Gifts',      'slug' => 'gifts'],
    ['label' => 'Aid',        'slug' => 'aid'],
    ['label' => 'Fees',       'slug' => 'fees'],
    ['label' => 'Sponsor',    'slug' => 'sponsor'],
    ['label' => 'Write-offs', 'slug' => 'writeoffs'],
];

$entries ??= [
    ['ref' => '#TX-98412', 'date' => '20 Jul, 09:14', 'title' => 'In-flight pool hold — booking #B-2201',      'meta' => 'M. Lawsan → In-flight pool',      'amount' => '−75 pts',     'amount_class' => 'error'],
    ['ref' => '#TX-98411', 'date' => '20 Jul, 08:52', 'title' => 'Gift — daily cap OK',                         'meta' => 'J. Kavipriya → T.H.K. Madushan', 'amount' => '15 pts',      'amount_class' => ''],
    ['ref' => '#TX-98407', 'date' => '19 Jul, 17:30', 'title' => 'In-flight pool release — booking #B-2188',    'meta' => 'In-flight → T.H.K. Madushan',    'amount' => '+25 pts',     'amount_class' => 'success'],
    ['ref' => '#TX-98395', 'date' => '19 Jul, 11:05', 'title' => 'Aid grant release — #A-1042',                 'meta' => 'Aid Pool → M. Lawsan',            'amount' => '+300 pts',    'amount_class' => 'success'],
    ['ref' => '#TX-98380', 'date' => '18 Jul, 21:40', 'title' => 'Late fee — booking #B-2160',                  'meta' => 'M. Lawsan → A. Akalvily',         'amount' => '10 pts',      'amount_class' => ''],
    ['ref' => '#TX-98371', 'date' => '18 Jul, 10:12', 'title' => 'Sponsor injection — INV-0312',                'meta' => 'Northwind Co → pools',            'amount' => '+10,000 pts', 'amount_class' => 'success'],
];

$search    ??= '';
$dateRange ??= '';
$division  ??= '';

$pageTitle = 'Global ledger';
$navActive = 'ledger';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Global ledger — append-only</h1>
    <button class="btn btn--ghost page-header__action" disabled title="Export coming soon">Export CSV</button>
</header>

<ul class="filter-pills">
    <?php foreach ($filters as $filter): ?>
        <li>
            <a
                class="pill<?= !empty($filter['active']) ? ' pill--active' : '' ?>"
                href="<?= base_url() ?>/admin/ledger?type=<?= e(rawurlencode($filter['slug'])) ?>"
                <?= !empty($filter['active']) ? 'aria-current="true"' : '' ?>
            ><?= e($filter['label']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="field-row">
    <div class="field">
        <input class="input" type="search" name="q" placeholder="Search reference / member" value="<?= e($search) ?>">
    </div>
    <div class="field">
        <select class="input" name="date_range">
            <option value="">Date range</option>
        </select>
    </div>
    <div class="field">
        <select class="input" name="division">
            <option value="">All divisions</option>
        </select>
    </div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Ref</th>
            <th>Date</th>
            <th>Description</th>
            <th style="text-align: right">Amount</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($entries as $entry): ?>
            <tr>
                <td><?= e($entry['ref']) ?></td>
                <td><?= e($entry['date']) ?></td>
                <td>
                    <strong><?= e($entry['title']) ?></strong>
                    <span class="text-muted"><?= e($entry['meta']) ?></span>
                </td>
                <td style="text-align: right"<?= $entry['amount_class'] !== '' ? ' class="color-' . e($entry['amount_class']) . '"' : '' ?>>
                    <?php if ($entry['amount_class'] === 'error'): ?>
                        <span style="color: var(--color-error)"><?= e($entry['amount']) ?></span>
                    <?php elseif ($entry['amount_class'] === 'success'): ?>
                        <span style="color: var(--color-success)"><?= e($entry['amount']) ?></span>
                    <?php else: ?>
                        <?= e($entry['amount']) ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="notice notice--info notice--full">
    Append-only: entries can never be edited or deleted. Corrections are new reversing entries. The nightly invariant check reconciles this ledger against every pool and wallet.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
