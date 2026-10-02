<?php

declare(strict_types=1);

/**
 * Sponsor notifications. Figma: "Sponsor Notifications" (387:75).
 *
 * @var array $notifications rows: id, icon, title, detail, time, unread, href
 * @var int   $page
 * @var bool  $hasNextPage
 * @var int   $unread
 * @var array|null $flash
 */

$pageTitle = 'Notifications';
$navActive = 'notifications';

$chrome = 'sponsor';
include __DIR__ . '/../../../partials/header.php';

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

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<?php include __DIR__ . '/../../../partials/notification-list.php'; ?>

<?php
$pageUrl = static fn (int $target): string => base_url() . '/sponsor/notifications?page=' . $target;
$pagerLabels = ['Newer', 'Older'];
include __DIR__ . '/../../../partials/pager.php';
?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
