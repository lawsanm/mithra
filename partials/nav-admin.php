<?php

declare(strict_types=1);

/**
 * Admin top navigation - Nav Bar component, actor = Admin (Figma 37:2).
 * 68px tall, 16px/48px padding, 1px bottom border.
 *
 * @var string $navActive    route key of the current page
 * @var array  $currentAdmin view data: initials
 */

$navActive = $navActive ?? '';

$currentAdmin = $currentAdmin ?? [
    'initials' => 'TM',
];

$navItems = [
    'dashboard'  => ['label' => 'Dashboard',   'href' => base_url() . '/admin/dashboard'],
    'divisions'  => ['label' => 'Divisions',    'href' => base_url() . '/admin/divisions'],
    'moderators' => ['label' => 'Moderators',   'href' => base_url() . '/admin/moderators'],
    'disputes'   => ['label' => 'Disputes',     'href' => base_url() . '/admin/disputes'],
    'categories' => ['label' => 'Categories',   'href' => base_url() . '/admin/categories'],
    'pools'      => ['label' => 'Pools',        'href' => base_url() . '/admin/pools'],
    'ledger'     => ['label' => 'Ledger',       'href' => base_url() . '/admin/ledger'],
    'users'      => ['label' => 'Users',        'href' => base_url() . '/admin/users'],
];

?>
<nav class="nav" aria-label="Admin">
    <a class="nav__brand" href="<?= base_url() ?>/admin/dashboard">
        <img class="nav__logo" src="<?= base_url() ?>/img/logo-deep-slate.svg" alt="">
        <span class="nav__wordmark">Mithra</span>
        <span class="nav__tagline">Lend · Share · Care</span>
    </a>

    <span class="nav__role-badge">Admin</span>

    <ul class="nav__items">
        <?php foreach ($navItems as $key => $navItem): ?>
            <li>
                <a
                    class="nav__link<?= $key === $navActive ? ' nav__link--active' : '' ?>"
                    href="<?= e($navItem['href']) ?>"
                    <?= $key === $navActive ? 'aria-current="page"' : '' ?>
                ><?= e($navItem['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="nav__spacer"></div>

    <div class="nav__actions">
        <a class="nav__bell" href="<?= base_url() ?>/admin/notifications" aria-label="Notifications">
            <svg class="icon" aria-hidden="true"><use href="#icon-bell"></use></svg>
        </a>
        <a class="avatar" href="<?= base_url() ?>/admin/settings"><?= e($currentAdmin['initials']) ?></a>
    </div>
</nav>
