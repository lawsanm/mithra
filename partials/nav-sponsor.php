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
    'dashboard'       => ['label' => 'Dashboard',        'href' => base_url() . '/sponsor/dashboard'],
    'purchase-points' => ['label' => 'Purchase Points',  'href' => base_url() . '/sponsor/purchase-points'],
    'csr-reports'     => ['label' => 'CSR Reports',      'href' => base_url() . '/sponsor/csr-reports'],
    'branding'        => ['label' => 'Branding',         'href' => base_url() . '/sponsor/branding'],
    'notifications'   => ['label' => 'Notifications',    'href' => base_url() . '/sponsor/notifications'],
];

?>
<nav class="nav" aria-label="Sponsor">
    <a class="nav__brand" href="<?= base_url() ?>/sponsor/dashboard">
        <img width="21" height="28" class="nav__logo" src="<?= base_url() ?>/img/logo-mark.svg" alt="">
        <span class="nav__wordmark">Mithra</span>
        <span class="nav__tagline">Lend · Share · Care</span>
    </a>

    <span class="nav__role-badge">Sponsor</span>

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
        <?php $accountRole = 'sponsor'; $accountInitials = $currentSponsor['initials']; include __DIR__ . '/account-menu.php'; ?>
        <span class="nav__company"><?= e($currentSponsor['company_name']) ?></span>
    </div>
</nav>
