<?php

declare(strict_types=1);

/**
 * The notification rows every role's Notifications page shows.
 *
 * @var array $notifications rows: icon, title, detail, time, unread, href
 */

?>
<?php if ($notifications === []): ?>
    <p class="empty-state">No notifications to show.</p>
<?php endif; ?>
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
