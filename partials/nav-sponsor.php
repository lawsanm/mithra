<?php

declare(strict_types=1);

/**
 * Sponsor top navigation — Nav Bar component, actor = Sponsor.
 * 68px tall, 16px/48px padding, 1px bottom border. Same shell as the member,
 * moderator and admin bars; the trailing slot carries the sponsor's company
 * name instead of a personal avatar.
 *
 * @var string $navActive       route key of the current page
 * @var array  $currentSponsor  view data: initials, company_name
 */

$navActive = $navActive ?? '';

$currentSponsor = $currentSponsor ?? [
    'initials'     => 'N',
    'company_name' => 'Northwind Co',
];

$navItems = [
    'dashboard'       => ['label' => 'Dashboard',        'href' => '/sponsor/dashboard'],
    'purchase-points' => ['label' => 'Purchase Points',  'href' => '/sponsor/purchase-points'],
    'csr-reports'     => ['label' => 'CSR Reports',      'href' => '/sponsor/csr-reports'],
    'branding'        => ['label' => 'Branding',         'href' => '/sponsor/branding'],
    'notifications'   => ['label' => 'Notifications',    'href' => '/sponsor/notifications'],
];

?>
<nav class="nav" aria-label="Sponsor">
    <a class="nav__brand" href="/sponsor/dashboard">
        <img class="nav__logo" src="/img/logo-deep-slate.svg" alt="">
        <span class="nav__wordmark">Mithra</span>
        <span class="nav__tagline">Lend · Share · Care</span>
    </a>

    <span class="nav__role-badge">Sponsor</span>

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
        <span class="avatar"><?= e($currentSponsor['initials']) ?></span>
        <span class="meta-pill"><?= e($currentSponsor['company_name']) ?></span>
    </div>
</nav>
