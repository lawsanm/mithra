<?php

declare(strict_types=1);

/**
 * Wallet. Figma: "Wallet" (97:295).
 *
 * @var array $balances  available and escrow cards
 * @var array $activity  ledger rows: icon, title, note, amount, tone, date
 * @var array  $filters  '' plus the PointLedger::GROUPS keys
 * @var string $filter   the active one
 * @var int    $page
 * @var bool   $hasNextPage
 */

$pageTitle = 'Wallet';
$navActive = 'wallet';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="page-header__title">Wallet</h1>

<div class="balance-grid">
    <?php foreach ($balances as $balance): ?>
        <div class="balance-card<?= $balance['dark'] ? ' balance-card--dark' : '' ?>">
            <span class="balance-card__label"><?= e($balance['label']) ?></span>
            <strong class="balance-card__value"><?= e($balance['value']) ?></strong>
            <span class="balance-card__note"><?= e($balance['note']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="actions">
    <?php // Without JS this lands on Gifts, which hosts the same form. ?>
    <a class="btn btn--primary" href="<?= base_url() ?>/gifts/new#send-gift" data-modal-open="send-gift">Send a gift</a>
    <a class="btn btn--ghost" href="<?= base_url() ?>/aid-grants/create">Request aid grant</a>
</div>

<h2 class="section-heading">Activity</h2>

<ul class="filter-pills" aria-label="Activity type">
    <?php foreach ($filters as $slug): ?>
        <li>
            <a class="pill<?= $filter === $slug ? ' pill--active' : '' ?>"
               href="<?= base_url() ?>/wallet<?= $slug === '' ? '' : '?filter=' . e($slug) ?>"
               <?= $filter === $slug ? 'aria-current="true"' : '' ?>><?= e($slug === '' ? 'All' : ucfirst($slug)) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($activity === []): ?>
    <p class="empty-state">No wallet activity recorded.</p>
<?php endif; ?>
<ul class="row-list">
    <?php foreach ($activity as $entry): ?>
        <li class="txn-row">
            <span class="txn-row__icon">
                <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-<?= e($entry['icon']) ?>"></use></svg>
            </span>
            <div class="txn-row__body">
                <span class="txn-row__name"><?= e($entry['title']) ?></span>
                <span class="txn-row__note"><?= e($entry['note']) ?></span>
            </div>
            <span class="txn-row__amount">
                <span class="txn-row__value txn-row__value--<?= e($entry['tone']) ?>"><?= e($entry['amount']) ?></span>
                <span class="txn-row__date"><?= e($entry['date']) ?></span>
            </span>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($page > 1 || $hasNextPage): ?>
    <div class="actions">
        <?php if ($page > 1): ?>
            <a class="btn btn--ghost" href="<?= base_url() ?>/wallet?<?= e(http_build_query(array_filter(['filter' => $filter, 'page' => $page - 1]))) ?>">Newer</a>
        <?php endif; ?>
        <?php if ($hasNextPage): ?>
            <a class="btn btn--ghost" href="<?= base_url() ?>/wallet?<?= e(http_build_query(array_filter(['filter' => $filter, 'page' => $page + 1]))) ?>">Older</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../partials/modal-send-gift.php'; ?>

<?php
$pageScripts = ['modal.js'];
include __DIR__ . '/../../partials/footer.php';
?>
