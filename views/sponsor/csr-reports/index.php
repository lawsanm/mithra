<?php

declare(strict_types=1);

/**
 * Sponsor CSR report. Figma: "CSR Report" (385:147).
 *
 * @var array $stats       four figures for the stat row
 * @var array $quarters    rows: label, meta, amount
 * @var string $reconcileNote
 */

$pageTitle = 'CSR report';
$navActive = 'csr-reports';

$chrome = 'sponsor';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Your CSR report</h1>
    <button class="btn btn--primary page-header__action" type="button" data-print>Print / Save as PDF</button>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <?php $statTone = 'primary'; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Quarterly breakdown</h2>
    </div>

    <ul class="row-list">
        <?php foreach ($quarters as $quarter): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($quarter['label']) ?></span>
                    <span class="list-row__meta"><?= e($quarter['meta']) ?></span>
                </div>
                <strong class="list-row__amount"><?= e($quarter['amount']) ?></strong>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<p class="page-intro__meta"><?= e($reconcileNote) ?></p>

<?php $pageScripts = ['reports.js']; include __DIR__ . '/../../../partials/footer.php'; ?>
