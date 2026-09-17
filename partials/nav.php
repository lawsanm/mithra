<?php

declare(strict_types=1);

/**
 * Member top navigation — Nav Bar component, actor = Member (Figma 793:963).
 * 68px tall, 16px/48px padding, 1px bottom border.
 *
 * @var string $navActive    route key of the current page
 * @var array  $currentMember view data: name, initials, points_balance
 */

$navActive = $navActive ?? '';

$currentMember = $currentMember ?? [
    'initials'       => 'ML',
    'points_balance' => '1,250 pts',
];

$navItems = [
    'dashboard' => ['label' => 'Dashboard',    'href' => base_url() . '/dashboard'],
    'browse'    => ['label' => 'Browse Items', 'href' => base_url() . '/items/browse'],
    'items'     => ['label' => 'My Items',     'href' => base_url() . '/items'],
    'bookings'  => ['label' => 'My Bookings',  'href' => base_url() . '/bookings'],
    'wallet'    => ['label' => 'Wallet',       'href' => base_url() . '/wallet'],
    'gifts'     => ['label' => 'Gifts',        'href' => base_url() . '/gifts'],
];

?>
<nav class="nav" aria-label="Main">
    <a class="nav__brand" href="<?= base_url() ?>/dashboard">
        <img width="21" height="28" class="nav__logo" src="<?= base_url() ?>/img/logo-mark.svg" alt="">
        <span class="nav__wordmark">Mithra</span>
        <span class="nav__tagline">Lend · Share · Care</span>
    </a>

    <button class="nav__toggle btn btn--ghost" type="button" data-nav-toggle aria-controls="primary-links" aria-expanded="false" hidden>Menu</button>
    <ul class="nav__items" id="primary-links">
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
        <a class="nav__bell" href="<?= base_url() ?>/notifications" aria-label="Notifications">
            <img class="nav__bell-icon" width="24" height="24" src="<?= base_url() ?>/img/nav-bell.svg" alt="">
        </a>
        <a class="meta-pill" href="<?= base_url() ?>/wallet"><?= e($currentMember['points_balance']) ?></a>
        <?php $accountRole = 'member'; $accountInitials = $currentMember['initials']; include __DIR__ . '/account-menu.php'; ?>
    </div>
</nav>
