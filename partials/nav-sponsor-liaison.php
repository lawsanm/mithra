<?php

declare(strict_types=1);

/**
 * Sponsor Liaison top navigation — Nav Bar component, actor = Sponsor Liaison
 * (Figma 377:46). 68px tall, 16px/48px padding, 1px bottom border. Same shell
 * as the other actor bars.
 *
 * @var string $navActive        route key of the current page
 * @var array  $currentLiaison   view data: initials
 */

$navActive = $navActive ?? '';

$currentLiaison = $currentLiaison ?? [
    'initials' => 'AA',
];

$navItems = [
    'dashboard'    => ['label' => 'Dashboard',   'href' => base_url() . '/sponsor-liaison/dashboard'],
    'sponsors'     => ['label' => 'Sponsors',    'href' => base_url() . '/sponsor-liaison/sponsors'],
    'purchases'    => ['label' => 'Purchases',   'href' => base_url() . '/sponsor-liaison/purchases'],
    'points-pool'  => ['label' => 'Points Pool', 'href' => base_url() . '/sponsor-liaison/points-pool'],
    'disasters'    => ['label' => 'Disasters',   'href' => base_url() . '/sponsor-liaison/disasters'],
    'aid-grants'   => ['label' => 'Aid Grants',  'href' => base_url() . '/sponsor-liaison/aid-grants'],
    'csr-reports'  => ['label' => 'CSR Reports', 'href' => base_url() . '/sponsor-liaison/csr-reports'],
];

?>
<nav class="nav" aria-label="Sponsor Liaison">
    <a class="nav__brand" href="<?= base_url() ?>/sponsor-liaison/dashboard">
        <img width="21" height="28" class="nav__logo" src="<?= base_url() ?>/img/logo-mark.svg" alt="">
        <span class="nav__wordmark">Mithra</span>
        <span class="nav__tagline">Lend · Share · Care</span>
    </a>

    <span class="nav__role-badge">Liaison</span>

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
        <?php $accountRole = 'sponsor-liaison'; $accountInitials = $currentLiaison['initials']; include __DIR__ . '/account-menu.php'; ?>
    </div>
</nav>
