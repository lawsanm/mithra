<?php

declare(strict_types=1);

/**
 * Disaster Mode control - toggle per division.
 *
 * @var array $divisions  rows: id, name, active(bool), meta, status, status_label
 */

$pageTitle = 'Disaster Mode control';
$navActive = 'dashboard';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Disaster Mode control</h1>
</header>

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<ul class="row-list">
    <?php foreach ($divisions as $div): ?>
        <li class="list-row">
            <div class="list-row__body">
                <span class="list-row__title"><?= e($div['name']) ?></span>
                <span class="list-row__meta"><?= e($div['meta']) ?></span>
            </div>
            <span class="badge badge--<?= e($div['status']) ?>"><?= e($div['status_label']) ?></span>
            <?php if ($div['active']): ?>
                <button class="btn btn--ghost" type="button" data-modal-open="modal-deactivate-disaster" data-division-id="<?= e((string) $div['id']) ?>" data-division-name="<?= e($div['name']) ?>">Deactivate</button>
            <?php else: ?>
                <button class="btn btn--primary" type="button" data-modal-open="modal-activate-disaster" data-division-id="<?= e((string) $div['id']) ?>">Activate</button>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>

<section class="panel">
    <h2 class="section-title">Disaster event history</h2>
    <p class="page-intro__meta">Recent activations and closures. Event records remain available for reporting and audit.</p>

    <?php if ($events === []): ?>
        <p class="empty-state__body">No disaster events have been recorded.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Division and event</th>
                    <th>Started by</th>
                    <th>Started</th>
                    <th>Planned end</th>
                    <th>Ended</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td>
                            <strong><?= e($event['division']) ?></strong>
                            <span class="text-muted"><?= e($event['reason']) ?></span>
                        </td>
                        <td><?= e($event['started_by']) ?></td>
                        <td><?= e($event['started_at']) ?></td>
                        <td><?= e($event['planned_end']) ?></td>
                        <td><?= e($event['ended_at']) ?></td>
                        <td><span class="badge badge--<?= e($event['status']) ?>"><?= e($event['status_label']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $pageUrl = static fn (int $target): string => base_url() . '/admin/disaster?page=' . $target;
        $pagerLabels = ['Newer', 'Older'];
        include __DIR__ . '/../../../partials/pager.php';
        ?>
    <?php endif; ?>
</section>

<div class="notice notice--info notice--full">
    Disaster Mode alerts sponsors and connects them with the division moderator. Relief is arranged off-platform; no points move. Each activation records a planned end date.
</div>

<?php include __DIR__ . '/../../../partials/modal-activate-disaster.php'; ?>
<?php include __DIR__ . '/../../../partials/modal-deactivate-disaster.php'; ?>

<?php $pageScripts = ['modal.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
