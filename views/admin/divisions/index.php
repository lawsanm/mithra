<?php

declare(strict_types=1);

/**
 * Division management list. Figma: "Division Management" (37:4).
 *
 * @var array $divisions  name, member_count, moderator_name, liaison_name, status, status_label, href
 */

$divisions ??= [
    ['name' => 'Kollupitiya',    'member_count' => 412, 'moderator_name' => 'A. Akalvily', 'liaison_name' => 'A. Akalvily', 'status' => 'success', 'status_label' => 'Healthy',       'href' => base_url() . '/admin/divisions/1'],
    ['name' => 'Wellawatte',     'member_count' => 386, 'moderator_name' => 'J. Kavipriya', 'liaison_name' => 'A. Akalvily', 'status' => 'error',   'status_label' => 'Disaster Mode', 'href' => base_url() . '/admin/divisions/2'],
    ['name' => 'Dehiwala',       'member_count' => 298, 'moderator_name' => 'T.H.K. Madushan', 'liaison_name' => 'A. Akalvily', 'status' => 'success', 'status_label' => 'Healthy',   'href' => base_url() . '/admin/divisions/3'],
    ['name' => 'Bambalapitiya',  'member_count' => 241, 'moderator_name' => null,           'liaison_name' => 'A. Akalvily', 'status' => 'warning', 'status_label' => 'No moderator',  'href' => base_url() . '/admin/divisions/4', 'vacant_days' => 12],
];

$pageTitle = 'Division management';
$navActive = 'divisions';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Division management</h1>
    <button class="btn btn--primary page-header__action" type="button" data-modal-open="modal-create-division">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
        Create division
    </button>
</header>

<div class="field" style="max-width: 360px;">
    <input class="input" type="search" placeholder="Search divisions" aria-label="Search divisions">
</div>

<ul class="row-list">
    <?php foreach ($divisions as $division): ?>
        <li class="list-row">
            <div class="list-row__body">
                <span class="list-row__title"><?= e($division['name']) ?></span>
                <span class="list-row__meta">
                    <?= e((string) $division['member_count']) ?> members ·
                    Mod: <?= $division['moderator_name'] !== null ? e($division['moderator_name']) : 'vacant — ' . e((string) ($division['vacant_days'] ?? '')) . ' days' ?> ·
                    Liaison: <?= e($division['liaison_name']) ?>
                </span>
            </div>
            <span class="badge badge--<?= e($division['status']) ?>"><?= e($division['status_label']) ?></span>
            <a class="btn btn--ghost" href="<?= e($division['href']) ?>">Manage</a>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../../partials/modal-create-division.php'; ?>

<?php $pageScripts = ['modal.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
