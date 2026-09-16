<?php

declare(strict_types=1);

/**
 * Admin dashboard.
 *
 * @var array $admin       name
 * @var array $globalMeta  division_count, member_count
 * @var array $stats       label, value, note, primary(bool)
 * @var array $invariant   passed(bool), last_run, summary
 * @var array $cronJobs    name, last_run, status, status_label
 */

$pageTitle = 'Dashboard';
$navActive = 'dashboard';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-intro page-intro--dashboard">
    <h1 class="page-intro__title">Welcome back, <?= e($admin['name']) ?></h1>
    <p class="page-intro__meta">System Administrator · <?= e((string) $globalMeta['division_count']) ?> GN divisions · <?= e((string) $globalMeta['member_count']) ?> members</p>
</header>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-card">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value <?= !empty($stat['error']) ? 'stat-card__value--warning' : 'stat-card__value--primary' ?>"><?= e($stat['value']) ?></strong>
            <span class="stat-card__note"><?= e($stat['note']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($invariant['passed']): ?>
    <div class="panel invariant-panel">
        <strong class="badge badge--success">Nightly invariant check passed</strong>
        <span class="invariant-panel__meta">Last run <?= e($invariant['last_run']) ?> · <?= e($invariant['summary']) ?></span>
    </div>
<?php else: ?>
    <div class="notice notice--error notice--full">
        <span>&times;</span>
        <span>NIGHTLY INVARIANT CHECK FAILED - <?= e($invariant['last_run']) ?>. <?= e($invariant['summary']) ?></span>
    </div>
<?php endif; ?>

<section class="panel section cron-panel">
    <div class="section__head">
        <h2 class="panel__title">Cron job health</h2>
        <a class="link section__action" href="<?= base_url() ?>/admin/cron">View all jobs</a>
    </div>

    <ul class="row-list">
        <?php foreach ($cronJobs as $job): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($job['name']) ?></span>
                    <span class="list-row__meta"><?= e($job['last_run']) ?></span>
                </div>
                <span class="badge badge--<?= e($job['status']) ?>"><?= e($job['status_label']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<div class="actions">
    <a class="btn btn--ghost" href="<?= base_url() ?>/admin/disaster">Disaster Mode</a>
    <a class="btn btn--ghost" href="<?= base_url() ?>/admin/pools/reserve">Reserve Pool</a>
    <a class="btn btn--primary" href="<?= base_url() ?>/admin/disputes">Open escalated disputes</a>
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
