<?php

declare(strict_types=1);

/**
 * Moderator performance overview.
 *
 * Each division's eligible candidates are listed under Moderators, where the
 * Admin appoints from them.
 *
 * @var array $moderators initials, name, division, meta, status, status_label, action_href
 */

$pageTitle = 'Moderator performance';
$navActive = 'moderators';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Moderator performance</h1>
</header>

<?php if ($moderators === []): ?>
    <p class="empty-state__body">No moderators are appointed yet.</p>
<?php endif; ?>

<ul class="row-list">
    <?php foreach ($moderators as $mod): ?>
        <li class="list-row">
            <span class="avatar"><?= e($mod['initials']) ?></span>
            <div class="list-row__body">
                <span class="list-row__title"><?= e($mod['name']) ?> - <?= e($mod['division']) ?></span>
                <span class="list-row__meta"><?= e($mod['meta']) ?></span>
            </div>
            <span class="badge badge--<?= e($mod['status']) ?>"><?= e($mod['status_label']) ?></span>
            <a class="btn btn--ghost" href="<?= e($mod['action_href']) ?>">View</a>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
