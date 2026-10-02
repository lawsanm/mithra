<?php

declare(strict_types=1);

/**
 * The notification rows every role's Notifications page shows.
 *
 * Opening one is a POST that marks it read and then follows its link — a GET
 * never changes data (Rules/CONVENTIONS.md §7). Each row can be dismissed.
 *
 * @var array $notifications rows: id, icon, title, detail, time, unread, href
 */

?>
<?php if ($notifications === []): ?>
    <p class="empty-state">No notifications to show.</p>
<?php endif; ?>
<ul class="row-list">
    <?php foreach ($notifications as $notification): ?>
        <li class="notification-row">
            <form class="notification-row__open" method="post" action="<?= base_url() ?>/notifications/<?= e((string) $notification['id']) ?>/read" novalidate>
                <?= csrf_field() ?>
                <button class="notification<?= $notification['unread'] ? ' notification--unread' : '' ?>" type="submit">
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
                </button>
            </form>
            <form method="post" action="<?= base_url() ?>/notifications/<?= e((string) $notification['id']) ?>/delete" novalidate>
                <?= csrf_field() ?>
                <button class="btn btn--ghost" type="submit" aria-label="Dismiss: <?= e($notification['title']) ?>">Dismiss</button>
            </form>
        </li>
    <?php endforeach; ?>
</ul>
