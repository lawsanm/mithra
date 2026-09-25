<?php

declare(strict_types=1);

/**
 * Six-pool accounting — admin global pool balances and invariant status.
 *
 * @var array $pools       list of pool cards: label, value, note
 * @var array $invariant   passed(bool), total, summary, last_run
 * @var array $jobs        recent job runs: name, last_run, status, status_label
 */

$pageTitle = 'Six-pool accounting';
$navActive = 'pools';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Six-pool accounting</h1>
    <div class="page-header__action actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/admin/pools/sponsor-ledger">Sponsor Fund Ledger</a>
        <a class="btn btn--ghost" href="<?= base_url() ?>/admin/ledger">Open ledger</a>
        <div class="inline-form" data-demo-form>
            <p class="demo-note">Preview only. Saving is not available yet.</p>
            <button class="btn btn--primary" type="submit" disabled>Run invariant check now</button>
        </div>
    </div>
</header>

<div class="stat-grid stat-grid--6">
    <?php foreach ($pools as $stat): ?>
        <?php $statTone = 'primary'; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<div class="notice notice--<?= $invariant['passed'] ? 'success' : 'error' ?> notice--full">
    <strong><?= $invariant['passed'] ? 'Invariant holds' : 'Invariant FAILED' ?></strong>
    <?= e($invariant['summary']) ?> · verified <?= e($invariant['last_run']) ?>
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
                    <span class="list-row__meta"><?= e($job['last_run']) ?></span>
                </div>
                <span class="badge badge--<?= e($job['status']) ?>"><?= e($job['status_label']) ?></span>
                <div class="inline-form" data-demo-form>
                    <p class="demo-note">Preview only. Saving is not available yet.</p>
                    <button class="btn btn--ghost" type="submit" disabled>Trigger</button>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<div class="notice notice--info notice--full">
    The 1:1 cash backing behind these balances is an accounting view visible only to Admin and the Sponsor Liaison. Members and sponsors see the public Transparency Dashboard instead. Points are never destroyed: the Reserve Pool covers shortfalls by paying lenders, and only Type A closures reach the Retired Pool, which is recycled to the Sponsor Pool.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
