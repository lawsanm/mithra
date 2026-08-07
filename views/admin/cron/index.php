<?php

declare(strict_types=1);

/**
 * Cron jobs — admin view of scheduled platform tasks.
 *
 * @var array $jobs  cron rows: name, description, schedule, last_run, next_run, status, status_label
 */

$jobs ??= [
    [
        'name'        => 'Nightly invariant check',
        'description' => 'Reconciles all six pool balances against the global ledger',
        'schedule'    => 'Daily at 02:00',
        'last_run'    => '20 Jul, 02:00',
        'next_run'    => '21 Jul, 02:00',
        'status'      => 'success',
        'status_label'=> 'OK',
    ],
    [
        'name'        => '48-hour auto-cancel sweep',
        'description' => 'Cancels unconfirmed bookings and refunds escrow',
        'schedule'    => 'Hourly',
        'last_run'    => '20 Jul, 09:00',
        'next_run'    => '20 Jul, 10:00',
        'status'      => 'success',
        'status_label'=> 'OK',
    ],
    [
        'name'        => 'Retired → Sponsor recycling',
        'description' => 'Moves Retired Pool balance back to Sponsor Pool',
        'schedule'    => '1st of month',
        'last_run'    => '01 Jul, 02:00',
        'next_run'    => '01 Aug, 02:00',
        'status'      => 'info',
        'status_label'=> 'Scheduled',
    ],
    [
        'name'        => 'Late-fee accrual',
        'description' => 'Charges daily late fees for overdue bookings',
        'schedule'    => 'Daily at 03:00',
        'last_run'    => '20 Jul, 03:00',
        'next_run'    => '21 Jul, 03:00',
        'status'      => 'success',
        'status_label'=> 'OK',
    ],
    [
        'name'        => 'Trust-score recalculation',
        'description' => 'Recomputes trust scores based on recent activity',
        'schedule'    => 'Weekly — Sunday 04:00',
        'last_run'    => '14 Jul, 04:00',
        'next_run'    => '21 Jul, 04:00',
        'status'      => 'success',
        'status_label'=> 'OK',
    ],
];

$pageTitle = 'Cron Jobs';
$navActive = 'cron';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Cron Jobs</h1>
</header>

<ul class="row-list">
    <?php foreach ($jobs as $job): ?>
        <li class="list-row">
            <div class="list-row__body">
                <span class="list-row__title"><?= e($job['name']) ?></span>
                <span class="list-row__meta"><?= e($job['description']) ?></span>
                <span class="list-row__meta"><?= e($job['schedule']) ?> · Last: <?= e($job['last_run']) ?> · Next: <?= e($job['next_run']) ?></span>
            </div>
            <span class="badge badge--<?= e($job['status']) ?>"><?= e($job['status_label']) ?></span>
            <button class="btn btn--ghost" type="button" data-modal-open="modal-trigger-job" data-job="<?= e($job['name']) ?>">Trigger now</button>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../../partials/modal-trigger-job.php'; ?>
<?php $pageScripts = ['modal.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
