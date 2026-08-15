<?php

declare(strict_types=1);

/**
 * Admin dashboard. 
 *
 * @var array $admin       name
 * @var array $globalMeta  division_count, member_count
 * @var array $stats       label, value, note, primary(bool)
 * @var array $invariant   passed(bool), last_run, summary, diff(array when failed)
 * @var array $cronJobs    name, schedule, last_run, status, status_label
 */

$admin ??= ['name' => 'Madushan'];

$globalMeta ??= ['division_count' => 38, 'member_count' => '2,412'];

$stats ??= [
    ['label' => 'Total members',      'value' => '2,412', 'note' => '+64 this month',       'primary' => true],
    ['label' => 'Active bookings',     'value' => '41',    'note' => '3,180 pts in escrow',  'primary' => false],
    ['label' => 'Escalated disputes',  'value' => '2',     'note' => '1 past 7-day timer',   'primary' => false, 'error' => true],
    ['label' => 'Points in system',    'value' => '121,300', 'note' => 'All wallets + pools', 'primary' => true],
];

$invariant ??= [
    'passed'   => true,
    'last_run' => '20 Jul, 02:00',
    'summary'  => 'total points in = total points out across all six pools · ledger hash verified',
    'diff'     => [],
];

$cronJobs ??= [
    ['name' => 'Nightly invariant check',  'schedule' => '02:00 daily',  'last_run' => 'last run 20 Jul, 02:00', 'status' => 'success', 'status_label' => 'On schedule'],
    ['name' => '48-hour auto-cancel sweep', 'schedule' => 'Hourly',      'last_run' => 'last run 20 Jul, 09:00', 'status' => 'success', 'status_label' => 'On schedule'],
    ['name' => 'Overdue booking flagger',   'schedule' => '06:00 daily', 'last_run' => 'last run 20 Jul, 06:00', 'status' => 'success', 'status_label' => 'On schedule'],
    ['name' => 'Write-off candidate scan',  'schedule' => 'Weekly · Sun', 'last_run' => 'last run 13 Jul, 03:00', 'status' => 'warning', 'status_label' => 'Overdue - expected 20 Jul'],
];

$pageTitle = 'Dashboard';
$navActive = 'dashboard';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-intro">
    <h1 class="page-intro__title">Good morning, <?= e($admin['name']) ?></h1>
    <p class="page-intro__meta">System Administrator · <?= e((string) $globalMeta['division_count']) ?> GN divisions · <?= e((string) $globalMeta['member_count']) ?> members</p>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-card">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value<?= !empty($stat['error']) ? '' : (!empty($stat['primary']) ? ' stat-card__value--primary' : '') ?>"
                <?php if (!empty($stat['error'])): ?> style="color: var(--color-error)"<?php endif; ?>
            ><?= e($stat['value']) ?></strong>
            <span class="stat-card__note"><?= e($stat['note']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($invariant['passed']): ?>
    <div class="notice notice--success notice--full">
        <strong>Nightly invariant check passed</strong>
        <span>Last run <?= e($invariant['last_run']) ?> · <?= e($invariant['summary']) ?></span>
    </div>
<?php else: ?>
    <div class="notice notice--error notice--full">
        <span>&times;</span>
        <span>NIGHTLY INVARIANT CHECK FAILED - <?= e($invariant['last_run']) ?>. <?= e($invariant['summary']) ?></span>
    </div>

    <?php if ($invariant['diff'] !== []): ?>
        <div class="form-card">
            <h3 class="form-card__legend">Reconciliation snapshot</h3>
            <?php foreach ($invariant['diff'] as $row): ?>
                <div class="line-item">
                    <span class="line-item__label"><?= e($row['label']) ?></span>
                    <span class="line-item__value"><?= e($row['value']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/admin/ledger/diff-report">Download diff report</a>
            <a class="btn btn--primary" href="<?= base_url() ?>/admin/ledger">Open ledger at <?= e($invariant['diff'][count($invariant['diff']) - 1]['label'] ?? '') ?></a>
        </div>
    <?php endif; ?>
<?php endif; ?>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Cron job health</h2>
        <a class="link section__action" href="<?= base_url() ?>/admin/cron">View all jobs</a>
    </div>

    <ul class="row-list">
        <?php foreach ($cronJobs as $job): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($job['name']) ?></span>
                    <span class="list-row__meta"><?= e($job['schedule']) ?> · <?= e($job['last_run']) ?></span>
                </div>
                <span class="badge badge--<?= e($job['status']) ?>"><?= e($job['status_label']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<div class="actions">
    <a class="btn btn--ghost" href="<?= base_url() ?>/admin/disaster">Disaster Mode</a>
    <a class="btn btn--ghost" href="<?= base_url() ?>/admin/pools/writeoffs">Review write-offs</a>
    <a class="btn btn--primary" href="<?= base_url() ?>/admin/disputes">Open escalated disputes</a>
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
