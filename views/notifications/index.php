<?php

declare(strict_types=1);

/**
 * Notifications. Figma: "Notifications" (94:154).
 *
 * @var array $filters      filter pills: label, slug, active
 * @var array $notifications rows: icon, title, detail, time, unread, href
 */

$pageTitle = 'Notifications';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Notifications</h1>
    <div class="page-header__action" data-demo-form>
        <p class="demo-note">Preview only. Saving is not available yet.</p>
        <button class="btn btn--ghost" type="submit" disabled>Mark all as read</button>
    </div>
</header>

<ul class="filter-pills">
    <?php foreach ($filters as $filter): ?>
        <li>
            <a
                class="pill<?= !empty($filter['active']) ? ' pill--active' : '' ?>"
                href="<?= base_url() ?>/notifications?type=<?= e(rawurlencode($filter['slug'])) ?>"
                <?= !empty($filter['active']) ? 'aria-current="true"' : '' ?>
            ><?= e($filter['label']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../partials/notification-list.php'; ?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
