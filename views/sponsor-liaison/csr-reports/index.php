<?php

declare(strict_types=1);

/**
 * CSR impact dashboard. Figma "CSR Impact Dashboard" (382:250).
 *
 * @var array $stats     four figures for the stat row
 * @var array $sponsors  rows: name, meta, contributed
 */

// Sample view data — replaced by the controller once SponsorLiaisonController lands.
$stats ??= [
    ['label' => 'Items shared',            'value' => '11,240', 'note' => '+18% vs last quarter'],
    ['label' => 'Households reached',      'value' => '2,412',  'note' => 'Across 6 GN divisions'],
    ['label' => 'Aid grants enabled',      'value' => '41',     'note' => '16,600 pts distributed'],
    ['label' => 'Est. community savings',  'value' => 'Rs 9.4M', 'note' => 'vs buying or renting retail'],
];

$sponsors ??= [
    ['name' => 'Northwind Co', 'meta' => 'funded 14 welcome bonuses · 9 aid grants · 2 festival drops', 'contributed' => '16,000 pts contributed'],
    ['name' => 'Texa',         'meta' => 'funded 8 welcome bonuses · 4 aid grants',                     'contributed' => '7,500 pts contributed'],
    ['name' => 'ACM Corp',     'meta' => 'funded 6 welcome bonuses · 3 aid grants · moderator stipends (Jun)', 'contributed' => '6,500 pts contributed'],
    ['name' => 'MNM',          'meta' => 'funded 100% Aid Pool · 11 aid grants',                        'contributed' => '7,000 pts contributed'],
];

$pageTitle = 'CSR impact';
$navActive = 'csr-reports';

include __DIR__ . '/../../../partials/header-sponsor-liaison.php';

?>

<header class="page-header">
    <h1 class="page-header__title">CSR impact</h1>
    <a class="btn btn--primary page-header__action" href="/sponsor-liaison/csr-reports/quarterly">Generate quarterly report</a>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-card">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value stat-card__value--primary"><?= e($stat['value']) ?></strong>
            <span class="stat-card__note"><?= e($stat['note']) ?></span>
        </div>
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
                <span class="list-row__title" style="color: var(--color-success-text);"><?= e($sponsor['contributed']) ?></span>
                <button class="btn btn--ghost" type="button">Report</button>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<div class="notice notice--info notice--full">
    Every sponsor receives the same recognition: sponsor wall, monthly newsletter, and optional tags on the bonuses and grants their contribution funded.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
