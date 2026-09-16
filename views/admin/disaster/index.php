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
