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

$pageTitle = 'Disaster Mode';
$navActive = 'disasters';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Disaster Mode</h1>
    <button class="btn btn--ghost page-header__action" type="button" data-print>Print incident report</button>
</header>

<section class="panel">
    <div class="panel__head">
        <span class="badge badge--<?= e($disaster['status']) ?>"><?= e($disaster['status_label']) ?></span>
        <p class="panel__note"><?= e($disaster['note']) ?></p>
        <div class="actions panel__actions">
            <a class="btn btn--primary" href="<?= base_url() ?>/sponsor-liaison/disasters/contributions">Verify contributions</a>
        </div>
    </div>
</section>

<div class="stat-grid stat-grid--3">
    <?php foreach ($stats as $stat): ?>
        <?php $statTone = ($stat['primary'] ?? true) ? 'primary' : ''; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Sponsor contributions</h2>
        <a class="link section__action" href="<?= base_url() ?>/sponsor-liaison/disasters/contributions">View all</a>
    </div>
    <p class="page-intro__meta">
        Sponsors give relief to the Moderator off-platform. Record each contribution with the sponsor's proof,
        let the Moderator confirm what they received, then verify it for the sponsor's CSR report.
    </p>
    <div class="actions">
        <a class="btn btn--primary" href="<?= base_url() ?>/sponsor-liaison/disasters/contributions/create">Record contribution</a>
        <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/disasters/contributions?status=ready">Ready to verify</a>
    </div>
</section>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Activation history</h2>
    </div>

    <ul class="row-list">
        <?php foreach ($history as $index => $entry): ?>
            <?php if ($index === 0): ?>
                <a class="list-row" href="<?= base_url() ?>/sponsor-liaison/disasters/connection">
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

<?php $pageScripts = ['print.js']; include __DIR__ . '/../../../partials/footer.php'; ?>
