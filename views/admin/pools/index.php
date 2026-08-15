<?php

declare(strict_types=1);

/**
 * Six-pool accounting — admin global pool balances and invariant status.
 *
 * @var array $pools       list of pool cards: label, value, note
 * @var array $invariant   passed(bool), total, summary, verified_at
 * @var array $jobs        scheduled jobs: name, schedule, last_run, status, status_label
 */

$pools ??= [
    ['label' => 'Sponsor Pool',   'value' => '48,200 pts',  'note' => 'welcome bonuses · stipends · festival drops'],
    ['label' => 'Aid Pool',       'value' => '12,750 pts',  'note' => 'aid grants · 15% of every injection'],
    ['label' => 'Reserve Pool',   'value' => '6,400 pts',   'note' => 'covers shortfalls — no negative balances'],
    ['label' => 'In-Flight Pool', 'value' => '3,180 pts',   'note' => 'rental charges + late-fee buffers, 41 bookings'],
    ['label' => 'Member Wallets', 'value' => '121,300 pts', 'note' => '2,412 wallets'],
    ['label' => 'Retired Pool',   'value' => '1,140 pts',   'note' => 'closed accounts & write-offs, awaiting recycling'],
];

$invariant ??= [
    'passed'      => true,
    'total'       => '192,970 pts',
    'summary'     => 'Σ (Sponsor + Aid + Reserve + In-Flight + Wallets + Retired) = 192,970 pts = Σ inflows − Σ outflows',
    'verified_at' => '20 Jul, 02:00',
];

$jobs ??= [
    ['name' => 'Nightly invariant check',      'schedule' => '02:00 daily · last 20 Jul', 'status' => 'success', 'status_label' => 'OK'],
    ['name' => '48-hour auto-cancel sweep',     'schedule' => 'Hourly · last 09:00',       'status' => 'success', 'status_label' => 'OK'],
    ['name' => 'Retired → Sponsor recycling',   'schedule' => 'Monthly · next 01 Aug',     'status' => 'info',    'status_label' => 'Scheduled'],
];

$pageTitle = 'Six-pool accounting';
$navActive = 'pools';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Six-pool accounting</h1>
    <div class="page-header__action actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/admin/pools/sponsor-ledger">Sponsor Fund Ledger</a>
        <a class="btn btn--ghost" href="<?= base_url() ?>/admin/ledger">Open ledger</a>
        <form method="post" action="<?= base_url() ?>/admin/pools/run-invariant" style="display:inline">
            <?= csrf_field() ?>
            <button class="btn btn--primary" type="submit">Run invariant check now</button>
        </form>
    </div>
</header>

<div class="stat-grid stat-grid--6">
    <?php foreach ($pools as $pool): ?>
        <div class="stat-card">
            <span class="stat-card__label"><?= e($pool['label']) ?></span>
            <strong class="stat-card__value stat-card__value--primary"><?= e($pool['value']) ?></strong>
            <span class="stat-card__note"><?= e($pool['note']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="notice notice--<?= $invariant['passed'] ? 'success' : 'error' ?> notice--full">
    <strong><?= $invariant['passed'] ? 'Invariant holds' : 'Invariant FAILED' ?></strong>
    <?= e($invariant['summary']) ?> · verified <?= e($invariant['verified_at']) ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Scheduled jobs</h2>
    </div>

    <ul class="row-list">
        <?php foreach ($jobs as $job): ?>
            <li class="list-row">
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($job['name']) ?></span>
                    <span class="list-row__meta"><?= e($job['schedule']) ?></span>
                </div>
                <span class="badge badge--<?= e($job['status']) ?>"><?= e($job['status_label']) ?></span>
                <form method="post" action="<?= base_url() ?>/admin/pools/trigger-job" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="job" value="<?= e($job['name']) ?>">
                    <button class="btn btn--ghost" type="submit">Trigger</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<div class="notice notice--info notice--full">
    The 1:1 cash backing behind these balances is an accounting view visible only to Admin and the Sponsor Liaison. Members and sponsors see the public Transparency Dashboard instead. Reserve write-offs exit via the Retired Pool.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
