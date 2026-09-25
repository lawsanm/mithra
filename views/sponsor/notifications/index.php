<?php

declare(strict_types=1);

/**
 * Sponsor notifications. Figma: "Sponsor Notifications" (387:75).
 *
 * @var array $notifications rows: icon, title, detail, time, unread, href
 */

// Sample view data — replaced by the controller once SponsorController lands.
$notifications ??= [
    [
        'icon'   => 'alert-triangle',
        'title'  => 'Disaster Mode active — Kollupitiya flooding',
        'detail' => 'Disaster response is now active. Your support can provide urgent relief and connect with the moderator on the ground.',
        'time'   => '2 hrs ago',
        'unread' => true,
        'href'   => base_url() . '/sponsor/disasters/1',
    ],
    [
        'icon'   => 'heart',
        'title'  => 'Aid request pending your response',
        'detail' => 'Your sponsorship can be a lifeline: dry rations and shelter materials are needed for 60 affected households.',
        'time'   => '1 day ago',
        'unread' => true,
        'href'   => base_url() . '/sponsor/disasters/1',
    ],
    [
        'icon'   => 'check-circle',
        'title'  => 'Q2 CSR report ready',
        'detail' => 'Your quarterly impact report is ready. 5,180 items shared and 22 aid grants enabled across the community.',
        'time'   => '5 days ago',
        'unread' => false,
        'href'   => base_url() . '/sponsor/csr-reports',
    ],
    [
        'icon'   => 'check-circle',
        'title'  => 'Disaster Mode deactivated',
        'detail' => 'The June response has closed. Thank you — your contribution reached 41 households.',
        'time'   => '12 days ago',
        'unread' => false,
        'href'   => base_url() . '/sponsor/csr-reports',
    ],
];

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
