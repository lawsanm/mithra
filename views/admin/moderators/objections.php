<?php

declare(strict_types=1);

/**
 * Objection management for a moderator appointment.
 *
 * @var array  $appointment  name, division, opened, closes, objection_count, status, status_label
 * @var array  $objections   rows: id, member, against, reason, date, status, status_label
 * @var bool   $windowExpired
 * @var bool   $allDismissed
 */

$appointment ??= [
    'name'            => 'Kamal Perera',
    'division'        => 'Kaduwela West',
    'opened'          => '22 Jul 2026',
    'closes'          => '29 Jul 2026',
    'objection_count' => 2,
    'status'          => 'warning',
    'status_label'    => 'In progress',
];

$objections ??= [
    [
        'id'           => 1,
        'member'       => 'Ruwan Fernando',
        'against'      => 'Kamal Perera',
        'reason'       => 'Concern about conflict of interest — operates a competing rental business',
        'date'         => '23 Jul 2026',
        'status'       => 'warning',
        'status_label' => 'Pending',
    ],
    [
        'id'           => 2,
        'member'       => 'Dilani Jayasuriya',
        'against'      => 'Kamal Perera',
        'reason'       => 'No specific concern — general disagreement with choice',
        'date'         => '25 Jul 2026',
        'status'       => 'neutral',
        'status_label' => 'Dismissed',
    ],
];

$windowExpired ??= false;
$allDismissed  ??= false;

$pageTitle = 'Objection management';
$navActive = 'moderators';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="/admin/moderators">Moderators</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current">Objections — <?= e($appointment['name']) ?></span>
</nav>

<header class="page-header">
    <h1 class="page-header__title">Objection management</h1>
</header>

<div class="form-card" style="width: 100%; max-width: 100%;">
    <div class="two-col" style="gap: var(--space-5);">
        <div>
            <h3 class="form-card__legend">Appointment: <?= e($appointment['name']) ?> → <?= e($appointment['division']) ?> moderator</h3>
            <div class="stat-grid stat-grid--3" style="margin-top: var(--space-4);">
                <div class="stat-card">
                    <span class="stat-card__label">Window opened</span>
                    <strong class="stat-card__value"><?= e($appointment['opened']) ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-card__label">Closes</span>
                    <strong class="stat-card__value"><?= e($appointment['closes']) ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-card__label">Objections raised</span>
                    <strong class="stat-card__value"><?= e((string) $appointment['objection_count']) ?></strong>
                </div>
            </div>
        </div>
        <div style="display: flex; align-items: center; justify-content: flex-end;">
            <span class="badge badge--<?= e($appointment['status']) ?>" style="font-size: var(--text-ui-label); padding: var(--space-2) var(--space-4);">
                <?= e($appointment['status_label']) ?>
            </span>
        </div>
    </div>
</div>

<section class="section">
    <h2 class="section__title">Raised objections</h2>

    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Against</th>
                    <th>Reason</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($objections as $obj): ?>
                    <tr>
                        <td><strong><?= e($obj['member']) ?></strong></td>
                        <td><?= e($obj['against']) ?></td>
                        <td><?= e($obj['reason']) ?></td>
                        <td><?= e($obj['date']) ?></td>
                        <td><span class="badge badge--<?= e($obj['status']) ?>"><?= e($obj['status_label']) ?></span></td>
                        <td>
                            <?php if ($obj['status'] === 'warning'): ?>
                                <div style="display: flex; gap: var(--space-2);">
                                    <button class="btn btn--ghost" type="button" onclick="document.getElementById('dismiss-modal').showModal(); document.getElementById('dismiss-member-name').textContent = '<?= e($obj['member']) ?>';">Dismiss</button>
                                    <button class="btn btn--danger" type="button" onclick="document.getElementById('uphold-modal').showModal(); document.getElementById('uphold-appointee-name').textContent = '<?= e($obj['against']) ?>';">Uphold</button>
                                </div>
                            <?php else: ?>
                                <span class="badge badge--neutral">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($windowExpired && $allDismissed): ?>
    <form method="post" action="/admin/moderators/<?= e(rawurlencode($appointment['name'])) ?>/finalise" style="margin-top: var(--space-6);">
        <?= csrf_field() ?>
        <button class="btn btn--primary" type="submit">Finalise appointment</button>
    </form>
<?php else: ?>
    <div class="notice notice--info notice--full" style="margin-top: var(--space-6);">
        Window still open — <?= e($appointment['closes']) ?>. Objections can be raised until the window closes.
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/modal-dismiss-objection.php'; ?>
<?php include __DIR__ . '/../../../partials/modal-uphold-objection.php'; ?>

<?php $pageScripts = []; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
