<?php

declare(strict_types=1);

/**
 * Global ledger — admin append-only transaction log with filters.
 *
 * @var array  $filters     label, slug, active(bool)
 * @var array  $entries     ref, date, title, meta, amount, amount_class
 * @var string $filter      the active reason group ('' for all)
 * @var string $search      member name search
 * @var int    $page
 * @var bool   $hasNextPage
 */

$pageQuery = static function (array $changes) use ($filter, $search, $page): string {
    return base_url() . '/admin/ledger?' . http_build_query(array_filter(
        $changes + ['filter' => $filter, 'q' => $search, 'page' => $page],
        static fn (mixed $value): bool => $value !== '' && $value !== 1
    ));
};

$pageTitle = 'Global ledger';
$navActive = 'ledger';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Global ledger — append-only</h1>
    <button class="btn btn--ghost page-header__action" disabled title="Export coming soon">Export CSV</button>
</header>

<ul class="filter-pills">
    <?php foreach ($filters as $pill): ?>
        <li>
            <a
                class="pill<?= !empty($pill['active']) ? ' pill--active' : '' ?>"
                href="<?= e($pageQuery(['filter' => $pill['slug'], 'page' => 1])) ?>"
                <?= !empty($pill['active']) ? 'aria-current="true"' : '' ?>
            ><?= e($pill['label']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<form class="field-row" method="get" action="<?= base_url() ?>/admin/ledger" role="search" novalidate>
    <input type="hidden" name="filter" value="<?= e($filter) ?>">
    <div class="field">
        <input class="input" type="search" name="q" placeholder="Search by member name" aria-label="Search by member name" value="<?= e($search) ?>">
    </div>
    <button class="btn btn--ghost" type="submit">Search</button>
</form>

<?php if ($entries === []): ?>
    <p class="empty-state__body">No ledger entries match.</p>
<?php endif; ?>

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
                        <span class="u-text-error"><?= e($entry['amount']) ?></span>
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

<?php if ($page > 1 || $hasNextPage): ?>
    <div class="actions">
        <?php if ($page > 1): ?>
            <a class="btn btn--ghost" href="<?= e($pageQuery(['page' => $page - 1])) ?>">Newer</a>
        <?php endif; ?>
        <?php if ($hasNextPage): ?>
            <a class="btn btn--ghost" href="<?= e($pageQuery(['page' => $page + 1])) ?>">Older</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="notice notice--info notice--full">
    Append-only: entries can never be edited or deleted. Corrections are new reversing entries. The nightly invariant check reconciles this ledger against every pool and wallet.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
