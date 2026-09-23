<?php

declare(strict_types=1);

/**
 * Division management list. Figma: "Division Management" (37:4).
 *
 * @var array      $divisions name, district, member_count, moderator_name, status, status_label, href
 * @var array|null $flash     result of the last create / archive
 */

$pageTitle = 'Division management';
$navActive = 'divisions';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Division management</h1>
    <button class="btn btn--primary page-header__action" type="button" data-modal-open="modal-create-division">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
        Create division
    </button>
</header>

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<div class="field" style="max-width: 360px;">
    <input class="input" type="search" placeholder="Search divisions" aria-label="Search divisions" data-filter-list="division-list">
</div>

<?php if ($divisions === []): ?>
    <p class="empty-state__body">No divisions yet. Create the first one to start accepting members.</p>
<?php endif; ?>

<ul class="row-list" id="division-list">
    <?php foreach ($divisions as $division): ?>
        <li class="list-row">
            <div class="list-row__body">
                <span class="list-row__title"><?= e($division['name']) ?></span>
                <span class="list-row__meta">
                    <?= e($division['district']) ?> · <?= e((string) $division['member_count']) ?> members ·
                    Moderator: <?= e($division['moderator_name'] ?? 'vacant') ?>
                </span>
            </div>
            <span class="badge badge--<?= e($division['status']) ?>"><?= e($division['status_label']) ?></span>
            <a class="btn btn--ghost" href="<?= e($division['href']) ?>">Manage</a>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../../partials/modal-create-division.php'; ?>

<?php $pageScripts = ['modal.js', 'list-filter.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
