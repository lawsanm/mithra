<?php

declare(strict_types=1);

/**
 * Admin notifications — platform-wide notification log.
 *
 * @var array $filters  filter pills
 * @var array $notices  notification rows: icon, title, meta, time, read
 */

$pageTitle = 'Notifications';
$navActive = 'notifications';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Notifications</h1>
    <div class="page-header__action" data-demo-form>
        <p class="demo-note">Preview only. Saving is not available yet.</p>
        <button class="btn btn--ghost" type="submit" disabled>Mark all read</button>
    </div>
</header>

<ul class="filter-pills">
    <?php foreach ($filters as $filter): ?>
        <li>
            <a
                class="pill<?= !empty($filter['active']) ? ' pill--active' : '' ?>"
                href="<?= base_url() ?>/admin/notifications?type=<?= e(rawurlencode($filter['slug'])) ?>"
                <?= !empty($filter['active']) ? 'aria-current="true"' : '' ?>
            ><?= e($filter['label']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<ul class="row-list">
    <?php foreach ($notices as $notice): ?>
        <li class="list-row<?= !$notice['read'] ? ' list-row--unread' : '' ?>">
            <span class="list-row__icon" aria-hidden="true"><?= e($notice['icon']) ?></span>
            <div class="list-row__body">
                <span class="list-row__title"><?= e($notice['title']) ?></span>
                <span class="list-row__meta"><?= e($notice['meta']) ?></span>
            </div>
            <span class="list-row__time"><?= e($notice['time']) ?></span>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
