<?php

declare(strict_types=1);

/**
 * Transparency dashboard. Figma: "Transparency Dashboard" (97:169).
 *
 * @var array $pools         six pool balances: label, value, note
 * @var array $invariant     nightly check badge and last-run line
 * @var array $contributions recent sponsor contributions: name, split, amount, date
 */

$pageTitle = 'Transparency dashboard';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="page-header__title">Transparency Dashboard</h1>

<p class="record-meta">
    Every point in the system, accounted for. Updated live, checked nightly.
</p>

<div class="pool-grid">
    <?php foreach ($pools as $pool): ?>
        <div class="pool-card">
            <span class="pool-card__label"><?= e($pool['label']) ?></span>
            <strong class="pool-card__value"><?= e($pool['value']) ?></strong>
            <span class="pool-card__note"><?= e($pool['note']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<section class="panel">
    <h2 class="visually-hidden">Accounting invariant</h2>
    <div class="media">
        <span class="badge badge--<?= e($invariant['tone']) ?>">
            <span aria-hidden="true">✓</span>
            <?= e($invariant['badge']) ?>
        </span>
        <span class="media__meta"><?= e($invariant['line']) ?></span>
    </div>
</section>

<h2 class="section-heading">Recent sponsor contributions</h2>

<ul class="row-list">
    <?php foreach ($contributions as $contribution): ?>
        <li class="txn-row">
            <div class="txn-row__body">
                <span class="txn-row__name"><?= e($contribution['name']) ?></span>
                <span class="txn-row__note"><?= e($contribution['split']) ?></span>
            </div>
            <span class="txn-row__amount">
                <span class="txn-row__value txn-row__value--in"><?= e($contribution['amount']) ?></span>
                <span class="txn-row__date"><?= e($contribution['date']) ?></span>
            </span>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
