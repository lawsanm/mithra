<?php

declare(strict_types=1);

/**
 * Disaster Mode control - toggle per division.
 *
 * @var array $divisions  rows: id, name, active(bool), meta, status, status_label
 */

$divisions ??= [
    [
        'id'           => 2,
        'name'         => 'Wellawatte',
        'active'       => true,
        'meta'         => 'Flooding · activated 15 Jul by Mod. J. Kavipriya · ends 22 Jul',
        'status'       => 'error',
        'status_label' => 'Active',
    ],
    [
        'id'           => 1,
        'name'         => 'Kollupitiya',
        'active'       => false,
        'meta'         => 'No active disaster',
        'status'       => 'success',
        'status_label' => 'Normal',
    ],
    [
        'id'           => 3,
        'name'         => 'Dehiwala',
        'active'       => false,
        'meta'         => 'No active disaster',
        'status'       => 'success',
        'status_label' => 'Normal',
    ],
    [
        'id'           => 4,
        'name'         => 'Bambalapitiya',
        'active'       => false,
        'meta'         => 'Deactivated 3 Jul · flood response closed after 37 hrs',
        'status'       => 'success',
        'status_label' => 'Normal',
    ],
];

$pageTitle = 'Disaster Mode control';
$navActive = 'dashboard';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Disaster Mode control</h1>
</header>

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

<div class="notice notice--info notice--full">
    Disaster Mode fast-tracks aid vouching, alerts sponsors, and relaxes late fees in the affected division. It auto-deactivates on the end date unless extended.
</div>

<?php include __DIR__ . '/../../../partials/modal-activate-disaster.php'; ?>
<?php include __DIR__ . '/../../../partials/modal-deactivate-disaster.php'; ?>

<?php $pageScripts = ['modal.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
