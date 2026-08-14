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
        'title'  => 'Disaster Mode active — Wellawatte flooding',
        'detail' => 'Disaster response is now active. Your support can provide urgent relief and connect with the moderator on the ground.',
        'time'   => '2 hrs ago',
        'unread' => true,
        'href'   => '/sponsor/disasters/1',
    ],
    [
        'icon'   => 'heart',
        'title'  => 'Aid request pending your response',
        'detail' => 'Your sponsorship can be a lifeline: dry rations and shelter materials are needed for 60 affected households.',
        'time'   => '1 day ago',
        'unread' => true,
        'href'   => '/sponsor/disasters/1',
    ],
    [
        'icon'   => 'check-circle',
        'title'  => 'Q2 CSR report ready',
        'detail' => 'Your quarterly impact report is ready. 5,180 items shared and 22 aid grants enabled across the community.',
        'time'   => '5 days ago',
        'unread' => false,
        'href'   => '/sponsor/csr-reports',
    ],
    [
        'icon'   => 'check-circle',
        'title'  => 'Disaster Mode deactivated',
        'detail' => 'The June response has closed. Thank you — your contribution reached 41 households.',
        'time'   => '12 days ago',
        'unread' => false,
        'href'   => '/sponsor/csr-reports',
    ],
];

$pageTitle = 'Notifications';
$navActive = 'notifications';

include __DIR__ . '/../../../partials/header-sponsor.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Notifications</h1>
    <form class="page-header__action" method="post" action="/sponsor/notifications/read-all">
        <?= csrf_field() ?>
        <button class="btn btn--ghost" type="submit">Mark all as read</button>
    </form>
</header>

<ul class="row-list">
    <?php foreach ($notifications as $notification): ?>
        <li>
            <a
                class="notification<?= $notification['unread'] ? ' notification--unread' : '' ?>"
                href="<?= e($notification['href']) ?>"
            >
                <svg class="notification__icon" aria-hidden="true">
                    <use href="#icon-<?= e($notification['icon']) ?>"></use>
                </svg>
                <span class="notification__body">
                    <span class="notification__title"><?= e($notification['title']) ?></span>
                    <span class="notification__detail"><?= e($notification['detail']) ?></span>
                </span>
                <span class="notification__aside">
                    <span class="notification__time"><?= e($notification['time']) ?></span>
                    <?php if ($notification['unread']): ?>
                        <span class="notification__dot"></span>
                        <span class="visually-hidden">Unread</span>
                    <?php endif; ?>
                </span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
