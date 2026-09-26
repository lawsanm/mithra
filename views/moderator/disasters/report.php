<?php

declare(strict_types=1);

/**
 * Disaster relief report — print view. Everything handed out for one
 * disaster, for the Admin, the Sponsor Liaison and the sponsors' CSR records.
 * The browser's print dialog saves it as a PDF.
 *
 * @var array $report    id, division, reason, period, active, prepared_by, prepared_at
 * @var array $stats     four figures: label, value
 * @var array $byType    rows: label, records, households, value
 * @var array $bySponsor rows: label, records, households, value
 * @var array $records   rows: date, type, what, location, households, sponsor, value
 */

$pageTitle = 'Relief report — ' . $report['reason'];
$navActive = 'disasters';

$chrome = 'moderator';
include __DIR__ . '/../../../partials/header.php';

$breakdowns = [
    'By relief type' => $byType,
    'By sponsor'     => $bySponsor,
];

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/moderator/disasters">Disaster relief</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Relief report</span>
</nav>

<header class="page-intro">
    <h1 class="page-intro__title">
        Disaster relief report — <?= e($report['division']) ?>
        <?php if ($report['active']): ?>
            <span class="badge badge--error">Disaster Mode active</span>
        <?php else: ?>
            <span class="badge badge--neutral">Ended</span>
        <?php endif; ?>
    </h1>
    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/moderator/disasters/<?= e((string) $report['id']) ?>/report/csv">Download CSV</a>
        <button class="btn btn--primary" type="button" data-print>Print / Save as PDF</button>
    </div>
</header>

<section class="panel">
    <dl class="facts">
        <div class="fact"><dt class="fact__label">Disaster</dt><dd class="fact__value"><?= e($report['reason']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Period</dt><dd class="fact__value"><?= e($report['period']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Prepared by</dt><dd class="fact__value">Moderator <?= e($report['prepared_by']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Generated</dt><dd class="fact__value"><?= e($report['prepared_at']) ?></dd></div>
    </dl>
</section>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <?php $statTone = 'primary'; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<?php if ($records === []): ?>
    <div class="empty-state">
        <p class="empty-state__title">No relief recorded for this disaster</p>
        <p class="empty-state__body">Records added on the Disaster relief page appear here.</p>
    </div>
<?php else: ?>
    <?php foreach ($breakdowns as $heading => $groups): ?>
        <section class="section">
            <h2 class="section__title"><?= e($heading) ?></h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e($heading === 'By sponsor' ? 'Sponsor' : 'Relief type') ?></th>
                        <th scope="col">Records</th>
                        <th scope="col">Households</th>
                        <th scope="col">Estimated value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                        <tr>
                            <td><strong><?= e($group['label']) ?></strong></td>
                            <td><?= e($group['records']) ?></td>
                            <td><?= e($group['households']) ?></td>
                            <td><?= e($group['value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endforeach; ?>

    <section class="section">
        <h2 class="section__title">Every record</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Type</th>
                    <th scope="col">What was given</th>
                    <th scope="col">Location</th>
                    <th scope="col">Households</th>
                    <th scope="col">Sponsor</th>
                    <th scope="col">Estimated value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= e($record['date']) ?></td>
                        <td><?= e($record['type']) ?></td>
                        <td><strong><?= e($record['what']) ?></strong></td>
                        <td><?= e($record['location']) ?></td>
                        <td><?= e($record['households']) ?></td>
                        <td><?= e($record['sponsor']) ?></td>
                        <td><?= e($record['value']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<p class="stat-card__note">
    Relief is arranged off-platform with sponsors' cash or goods (Plan §14.1). These records are kept by the
    division's moderator for reporting only; no points moved.
    <?php if ($report['active']): ?>
        The disaster is still active, so these figures may change.
    <?php endif; ?>
</p>

<div class="actions">
    <a class="btn btn--ghost" href="<?= base_url() ?>/moderator/disasters">Back to disaster relief</a>
</div>

<?php $pageScripts = ['print.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
