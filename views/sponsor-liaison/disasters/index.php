<?php

declare(strict_types=1);

/**
 * Disaster Mode overview for the liaison's coverage area. Figma "Disasters"
 * (378:231).
 *
 * @var array $disaster  active(bool), status, status_label, note
 * @var array $stats     three figures for the stat row
 * @var array $history   rows: period, meta, duration
 */

// Sample view data — replaced by the controller once SponsorLiaisonController lands.
$disaster ??= [
    'active'       => false,
    'status'       => 'success',
    'status_label' => 'Currently inactive',
    'note'         => 'Pool payouts are operating normally across all divisions.',
];

$stats ??= [
    ['label' => 'Activations (all time)', 'value' => '4',      'note' => 'Most recent: 15 Jul'],
    ['label' => 'Sponsors notified',      'value' => '23',     'note' => 'Across all activations'],
    ['label' => 'Avg. duration',          'value' => '48 hrs', 'note' => 'From activation to close', 'primary' => false],
];

$history ??= [
    ['period' => 'Activated 15 Jul, 09:20  →  deactivated 17 Jul, 06:20', 'meta' => 'Triggered by Mod. J. Kavipriya  ·  13 sponsors notified', 'duration' => '45 hrs'],
    ['period' => 'Activated 01 Jul, 20:30  →  deactivated 03 Jul, 09:30', 'meta' => 'Triggered by Mod. A. Akalvily  ·  18 sponsors notified',  'duration' => '37 hrs'],
    ['period' => 'Activated 15 Jun, 12:00  →  deactivated 18 Jun, 12:00', 'meta' => 'Triggered by Mod. J. Kavipriya  ·  23 sponsors notified', 'duration' => '72 hrs'],
];

$pageTitle = 'Disaster Mode';
$navActive = 'disasters';

include __DIR__ . '/../../../partials/header-sponsor-liaison.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Disaster Mode</h1>
    <button class="btn btn--ghost page-header__action" type="button">Export incident report</button>
</header>

<section class="panel">
    <div class="panel__head">
        <span class="badge badge--<?= e($disaster['status']) ?>"><?= e($disaster['status_label']) ?></span>
        <p class="panel__note"><?= e($disaster['note']) ?></p>
        <div class="actions panel__actions">
            <button class="btn btn--ghost" type="button">Notify sponsors</button>
            <button class="btn btn--primary" type="button">Activate Disaster Mode</button>
        </div>
    </div>
</section>

<div class="stat-grid stat-grid--3">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-card">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value<?= ($stat['primary'] ?? true) ? ' stat-card__value--primary' : '' ?>"><?= e($stat['value']) ?></strong>
            <span class="stat-card__note"><?= e($stat['note']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Activation history</h2>
    </div>

    <ul class="row-list">
        <?php foreach ($history as $index => $entry): ?>
            <?php if ($index === 0): ?>
                <a class="list-row" href="/sponsor-liaison/disasters/connection">
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($entry['period']) ?></span>
                        <span class="list-row__meta"><?= e($entry['meta']) ?></span>
                    </div>
                    <span class="list-row__title"><?= e($entry['duration']) ?></span>
                </a>
            <?php else: ?>
                <li class="list-row">
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($entry['period']) ?></span>
                        <span class="list-row__meta"><?= e($entry['meta']) ?></span>
                    </div>
                    <span class="list-row__title"><?= e($entry['duration']) ?></span>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
