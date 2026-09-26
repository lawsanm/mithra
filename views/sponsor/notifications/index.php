<?php

declare(strict_types=1);

/**
 * Sponsor notifications. Figma: "Sponsor Notifications" (387:75).
 *
 * @var array $notifications rows: icon, title, detail, time, unread, href
 */

$pageTitle = 'Notifications';
$navActive = 'notifications';

$chrome = 'sponsor';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Notifications</h1>
    <div class="page-header__action" data-demo-form>
        <p class="demo-note">Preview only. Saving is not available yet.</p>
        <button class="btn btn--ghost" type="submit" disabled>Mark all as read</button>
    </div>
</header>

<?php include __DIR__ . '/../../../partials/notification-list.php'; ?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
