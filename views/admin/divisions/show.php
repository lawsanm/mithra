<?php

declare(strict_types=1);

/**
 * Division detail view.
 *
 * @var array      $division id, name, district, archived, moderator_name, moderator_since, status, status_label
 * @var array      $stats    label, value, note, error(bool)
 * @var array|null $flash    result of the last edit
 */

$pageTitle = $division['name'];
$navActive = 'divisions';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/admin/divisions">Divisions</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current"><?= e($division['name']) ?></span>
</nav>

<header class="page-intro">
    <h1 class="page-intro__title">
        <?= e($division['name']) ?>
        <span class="badge badge--<?= e($division['status']) ?>"><?= e($division['status_label']) ?></span>
    </h1>
    <?php if (!$division['archived']): ?>
        <div class="actions">
            <button class="btn btn--ghost" type="button" data-modal-open="modal-edit-division">Edit division</button>
            <form method="post" action="<?= base_url() ?>/admin/divisions/<?= e((string) $division['id']) ?>/archive"
                data-confirm="Archive this division? It will no longer accept new members." novalidate>
                <?= csrf_field() ?>
                <button class="btn btn--ghost u-text-error" type="submit">Archive division</button>
            </form>
        </div>
    <?php endif; ?>
</header>
<p class="page-intro__meta"><?= e($division['district']) ?> District</p>

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<div class="stat-grid stat-grid--3">
    <?php foreach ($stats as $stat): ?>
        <?php $statTone = !empty($stat['error']) ? 'error' : 'primary'; include __DIR__ . '/../../../partials/stat-card.php'; ?>
    <?php endforeach; ?>
</div>

<div class="form-card form-card--wide">
    <h3 class="form-card__legend">Division staff</h3>

    <div class="list-row">
        <?php if ($division['moderator_name'] === null): ?>
            <span class="avatar">—</span>
            <div class="list-row__body">
                <span class="list-row__title u-text-error">Moderator — vacant</span>
                <span class="list-row__meta">Until one is appointed, the Admin approves this division's new members.</span>
            </div>
            <a class="btn btn--primary" href="<?= base_url() ?>/admin/moderators/appoint/<?= e((string) $division['id']) ?>">Appoint moderator</a>
        <?php else: ?>
            <span class="avatar"><?= e(User::initials($division['moderator_name'])) ?></span>
            <div class="list-row__body">
                <span class="list-row__title">Moderator — <?= e($division['moderator_name']) ?></span>
                <span class="list-row__meta"><?= $division['moderator_since'] === '' ? '' : 'Appointed ' . e($division['moderator_since']) ?></span>
            </div>
            <a class="btn btn--ghost" href="<?= base_url() ?>/admin/moderators?division=<?= e((string) $division['id']) ?>">View</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$division['archived']): ?>
    <?php include __DIR__ . '/../../../partials/modal-edit-division.php'; ?>
<?php endif; ?>
<?php $pageScripts = ['modal.js', 'confirm.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
