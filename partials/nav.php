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
    'dashboard' => ['label' => 'Dashboard',    'href' => '/dashboard'],
    'browse'    => ['label' => 'Browse Items', 'href' => '/items/browse'],
    'items'     => ['label' => 'My Items',     'href' => '/items'],
    'bookings'  => ['label' => 'My Bookings',  'href' => '/bookings'],
    'wallet'    => ['label' => 'Wallet',       'href' => '/wallet'],
    'gifts'     => ['label' => 'Gifts',        'href' => '/gifts'],
];

?>
<nav class="nav" aria-label="Main">
    <a class="nav__brand" href="/dashboard">
        <img class="nav__logo" src="/img/logo-deep-slate.svg" alt="">
        <span class="nav__wordmark">Mithra</span>
        <span class="nav__tagline">Lend · Share · Care</span>
    </a>

    <ul class="nav__items">
        <?php foreach ($navItems as $key => $item): ?>
            <li>
                <a
                    class="nav__link<?= $key === $navActive ? ' nav__link--active' : '' ?>"
                    href="<?= e($item['href']) ?>"
                    <?= $key === $navActive ? 'aria-current="page"' : '' ?>
                ><?= e($item['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="nav__spacer"></div>

    <div class="nav__actions">
        <a class="nav__bell" href="/notifications" aria-label="Notifications">
            <svg class="icon" aria-hidden="true"><use href="#icon-bell"></use></svg>
        </a>
        <a class="meta-pill" href="/wallet"><?= e($currentMember['points_balance']) ?></a>
        <a class="avatar" href="/profile"><?= e($currentMember['initials']) ?></a>
    </div>
</nav>
