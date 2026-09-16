<?php

declare(strict_types=1);

/**
 * Cron jobs — admin view of scheduled platform tasks.
 *
 * @var array $jobs  recorded job runs: name, description (the run's notes), last_run, status, status_label
 */

$pageTitle = 'Cron Jobs';
$navActive = 'cron';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Cron Jobs</h1>
</header>

<?php if ($jobs === []): ?>
    <p class="empty-state__body">No scheduled job has run yet.</p>
<?php endif; ?>

<ul class="row-list">
    <?php foreach ($jobs as $job): ?>
        <li class="list-row">
            <div class="list-row__body">
                <span class="list-row__title"><?= e($job['name']) ?></span>
                <span class="list-row__meta"><?= e($job['description']) ?></span>
                <span class="list-row__meta"><?= e($job['last_run']) ?></span>
            </div>
            <span class="badge badge--<?= e($job['status']) ?>"><?= e($job['status_label']) ?></span>
            <button class="btn btn--ghost" type="button" data-modal-open="modal-trigger-job" data-job="<?= e($job['name']) ?>" data-last-run="<?= e($job['last_run']) ?>" data-description="<?= e($job['description']) ?>">Trigger now</button>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../../partials/modal-trigger-job.php'; ?>
<?php $pageScripts = ['modal.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
