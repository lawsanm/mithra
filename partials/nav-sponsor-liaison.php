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
    'dashboard'    => ['label' => 'Dashboard',   'href' => '/sponsor-liaison/dashboard'],
    'sponsors'     => ['label' => 'Sponsors',    'href' => '/sponsor-liaison/sponsors'],
    'purchases'    => ['label' => 'Purchases',   'href' => '/sponsor-liaison/purchases'],
    'points-pool'  => ['label' => 'Points Pool', 'href' => '/sponsor-liaison/points-pool'],
    'disasters'    => ['label' => 'Disasters',   'href' => '/sponsor-liaison/disasters'],
    'aid-grants'   => ['label' => 'Aid Grants',  'href' => '/sponsor-liaison/aid-grants'],
    'csr-reports'  => ['label' => 'CSR Reports', 'href' => '/sponsor-liaison/csr-reports'],
];

?>
<nav class="nav" aria-label="Sponsor Liaison">
    <a class="nav__brand" href="/sponsor-liaison/dashboard">
        <img class="nav__logo" src="/img/logo-deep-slate.svg" alt="">
        <span class="nav__wordmark">Mithra</span>
        <span class="nav__tagline">Lend · Share · Care</span>
    </a>

    <span class="nav__role-badge">Liaison</span>

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
        <span class="avatar"><?= e($currentLiaison['initials']) ?></span>
    </div>
</nav>
