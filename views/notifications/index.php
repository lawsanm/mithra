<?php

declare(strict_types=1);

/**
 * Notifications. Figma: "Notifications" (94:154).
 *
 * @var array $filters       filter pills: label, slug, active
 * @var array $notifications rows: id, icon, title, detail, time, unread, href
 * @var string $group        the active pill's slug
 * @var int   $page
 * @var bool  $hasNextPage
 * @var int   $unread        unread count
 * @var array|null $flash
 */

$pageTitle = 'Notifications';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Notifications</h1>
    <?php if ($unread > 0): ?>
        <form class="page-header__action" method="post" action="<?= base_url() ?>/notifications/read-all" novalidate>
            <?= csrf_field() ?>
            <button class="btn btn--ghost" type="submit">Mark all as read (<?= e((string) $unread) ?>)</button>
        </form>
    <?php endif; ?>
</header>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

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

<?php
$pageUrl = static fn (int $target): string => base_url() . '/notifications?' . http_build_query(array_filter(['type' => $group, 'page' => $target]));
$pagerLabels = ['Newer', 'Older'];
include __DIR__ . '/../../partials/pager.php';
?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
