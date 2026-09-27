<?php

declare(strict_types=1);

/**
 * Points pool. Figma "Points Pool" (378:148).
 *
 * @var array $stats  four figures for the stat row
 * @var array $ledger  rows: date, title, meta, amount, amount_class, balance_after
 */

$pageTitle = 'Points pool';
$navActive = 'points-pool';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Points pool</h1>
    <a class="btn btn--primary page-header__action" href="<?= base_url() ?>/sponsor-liaison/points-pool/reserve-topup">Top up Reserve</a>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <?php $statTone = $stat['class']; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<section class="section" id="pool-ledger">
    <div class="section__head">
        <h2 class="section__title">Pool ledger</h2>
        <a class="link section__action" href="#pool-ledger">Pool ledger</a>
    </div>

    <ul class="row-list">
        <?php foreach ($ledger as $entry): ?>
            <li class="list-row">
                <span style="width: 70px; flex-shrink: 0; color: var(--color-text-muted); font-size: var(--text-ui-body);"><?= e($entry['date']) ?></span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($entry['title']) ?></span>
                    <span class="list-row__meta"><?= e($entry['meta']) ?></span>
                </div>
                <strong class="list-row__title" style="color: var(--color-<?= e($entry['amount_class']) ?>-text);"><?= e($entry['amount']) ?></strong>
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 2px;">
                    <span class="list-row__title"><?= e($entry['balance_after']) ?></span>
                    <span class="stat-card__note">movement</span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
