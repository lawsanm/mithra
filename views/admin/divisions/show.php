<?php

declare(strict_types=1);

/**
 * Division detail view.
 *
 * @var array $division     id, name, status, status_label
 * @var array $stats        label, value, note, primary(bool)
 * @var array $staff        moderator(array|null), liaison(array|null)
 */

$division ??= [
    'id'           => 4,
    'name'         => 'Bambalapitiya',
    'status'       => 'warning',
    'status_label' => 'No moderator — 12 days',
];

$stats ??= [
    ['label' => 'Members',         'value' => '241', 'note' => '+8 this month',     'primary' => true],
    ['label' => 'Active listings', 'value' => '187', 'note' => '12 pending approval', 'primary' => true],
    ['label' => 'Open disputes',   'value' => '1',   'note' => 'escalated to you',  'primary' => false, 'error' => true],
];

$staff ??= [
    'moderator' => null,
    'moderator_vacant_since' => '8 Jul',
    'moderator_fallback' => 'Pending approvals are routed to the Dehiwala moderator meanwhile',
    'liaison' => [
        'name'     => 'A. Akalvily',
        'initials' => 'AA',
        'meta'     => 'Covers 6 divisions in Colombo District · assigned Jan 2026',
    ],
];

$pageTitle = $division['name'];
$navActive = 'divisions';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="/admin/divisions">Divisions</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current"><?= e($division['name']) ?></span>
</nav>

<header class="page-intro">
    <h1 class="page-intro__title">
        <?= e($division['name']) ?>
        <span class="badge badge--<?= e($division['status']) ?>"><?= e($division['status_label']) ?></span>
    </h1>
</header>

<div class="stat-grid stat-grid--3">
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

<div class="form-card" style="width: 100%; max-width: 100%;">
    <h3 class="form-card__legend">Division staff</h3>

    <div class="list-row">
        <?php if ($staff['moderator'] === null): ?>
            <span class="avatar">—</span>
            <div class="list-row__body">
                <span class="list-row__title" style="color: var(--color-error)">Moderator - vacant since <?= e($staff['moderator_vacant_since']) ?></span>
                <span class="list-row__meta"><?= e($staff['moderator_fallback']) ?></span>
            </div>
            <a class="btn btn--primary" href="/admin/moderators/appoint/<?= e((string) $division['id']) ?>">Appoint moderator</a>
        <?php else: ?>
            <span class="avatar"><?= e($staff['moderator']['initials']) ?></span>
            <div class="list-row__body">
                <span class="list-row__title">Moderator - <?= e($staff['moderator']['name']) ?></span>
                <span class="list-row__meta"><?= e($staff['moderator']['meta']) ?></span>
            </div>
            <a class="btn btn--ghost" href="/admin/moderators">View</a>
        <?php endif; ?>
    </div>

    <?php if ($staff['liaison'] !== null): ?>
        <div class="list-row">
            <span class="avatar"><?= e($staff['liaison']['initials']) ?></span>
            <div class="list-row__body">
                <span class="list-row__title">Sponsor Liaison - <?= e($staff['liaison']['name']) ?></span>
                <span class="list-row__meta"><?= e($staff['liaison']['meta']) ?></span>
            </div>
            <span class="btn btn--ghost" style="opacity:0.5;pointer-events:none;">Reassign liaison</span>
        </div>
    <?php endif; ?>
</div>

<div class="notice notice--info notice--full">
    Appointing a moderator uses the eligible candidate pool and the appointment flow - including the 7-day community objection window for launch-phase nominations.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
