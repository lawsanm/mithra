<?php

declare(strict_types=1);

/**
 * Notifications. Figma: "Notifications" (94:154).
 *
 * @var array $filters      filter pills: label, slug, active
 * @var array $notifications rows: icon, title, detail, time, unread, href
 */

// Sample view data — replaced by the controller once NotificationController lands.
$filters ??= [
    ['label' => 'All',         'slug' => '',      'active' => true],
    ['label' => 'Bookings',    'slug' => 'bookings'],
    ['label' => 'Gifts & aid', 'slug' => 'gifts-aid'],
    ['label' => 'System',      'slug' => 'system'],
];

$notifications ??= [
    [
        'icon'   => 'handshake',
        'title'  => 'Madushan accepted your request for Bosch Cordless Drill',
        'detail' => 'Handover step is now open — upload your photos.',
        'time'   => '10 min ago',
        'unread' => true,
        'href'   => base_url() . '/bookings/1?state=handover',
    ],
    [
        'icon'   => 'alert-triangle',
        'title'  => 'Return due tomorrow: Bosch Cordless Drill',
        'detail' => 'Return by 17 Jul to keep your on-time streak.',
        'time'   => '2 h ago',
        'unread' => true,
        'href'   => base_url() . '/bookings/1',
    ],
    [
        'icon'   => 'gift',
        'title'  => 'Kavipriya sent you a gift of 10 pts',
        'detail' => '“Thanks for the jumper cables last week!”',
        'time'   => 'Yesterday',
        'unread' => false,
        'href'   => base_url() . '/gifts?box=received',
    ],
    [
        'icon'   => 'heart',
        'title'  => 'Your aid grant moved to liaison approval',
        'detail' => 'Moderator A. Akalvily vouched for request #A-1042.',
        'time'   => '12 Jul',
        'unread' => false,
        'href'   => base_url() . '/aid-grants/1042',
    ],
    [
        'icon'   => 'check-circle',
        'title'  => 'Listing approved: Pressure Washer',
        'detail' => 'Your listing is now visible to Kollupitiya members.',
        'time'   => '10 Jul',
        'unread' => false,
        'href'   => base_url() . '/items/3/edit',
    ],
];

$pageTitle = 'Notifications';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Notifications</h1>
    <form class="page-header__action" method="post" action="<?= base_url() ?>/notifications/read-all">
        <?= csrf_field() ?>
        <button class="btn btn--ghost" type="submit">Mark all as read</button>
    </form>
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

<?php include __DIR__ . '/../../partials/footer.php'; ?>
