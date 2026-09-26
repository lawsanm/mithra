<?php

declare(strict_types=1);

/**
 * CSR impact dashboard. Figma "CSR Impact Dashboard" (382:250).
 *
 * @var array $stats     four figures for the stat row
 * @var array $sponsors  rows: name, meta, contributed
 */

$pageTitle = 'CSR impact';
$navActive = 'csr-reports';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">CSR impact</h1>
    <a class="btn btn--primary page-header__action" href="<?= base_url() ?>/sponsor-liaison/csr-reports/quarterly">Generate quarterly report</a>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <?php $statTone = 'primary'; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Impact by sponsor</h2>
    </div>

    <ul class="row-list">
        <?php foreach ($sponsors as $sponsor): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($sponsor['name']) ?></span>
                    <span class="list-row__meta"><?= e($sponsor['meta']) ?></span>
                </div>
                <span class="list-row__title u-text-success"><?= e($sponsor['contributed']) ?></span>
                <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/csr-reports/quarterly">Report</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<div class="notice notice--info notice--full">
    Every sponsor receives the same recognition: sponsor wall, monthly newsletter, and optional tags on the bonuses and grants their contribution funded.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
