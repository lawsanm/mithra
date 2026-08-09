<?php

declare(strict_types=1);

/**
 * Moderator top navigation — Nav Bar component, actor = Moderator.
 * 68px tall, 16px/48px padding, 1px bottom border. Same shell as the member and
 * admin bars; it carries the conduct bond the moderator has staked.
 *
 * @var string $navActive        route key of the current page
 * @var array  $currentModerator view data: initials, bond
 */

$navActive = $navActive ?? '';

$currentModerator = $currentModerator ?? [
    'initials' => 'JK',
    'bond'     => 'Bond: 500 pts',
];

$navItems = [
    'dashboard'         => ['label' => 'Dashboard',     'href' => '/moderator/dashboard'],
    'verifications'     => ['label' => 'Verifications', 'href' => '/moderator/verifications'],
    'listing-approvals' => ['label' => 'Approvals',     'href' => '/moderator/listing-approvals'],
    'cases'             => ['label' => 'Cases',         'href' => '/moderator/cases'],
    'disasters'         => ['label' => 'Disasters',     'href' => '/moderator/disasters'],
];

?>
<nav class="nav" aria-label="Moderator">
    <a class="nav__brand" href="/moderator/dashboard">
        <img class="nav__logo" src="/img/logo-deep-slate.svg" alt="">
        <span class="nav__wordmark">Mithra</span>
        <span class="nav__tagline">Lend · Share · Care</span>
    </a>

    <span class="nav__role-badge">Moderator</span>

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
        <a class="nav__bell" href="/notifications" aria-label="Notifications">
            <svg class="icon" aria-hidden="true"><use href="#icon-bell"></use></svg>
        </a>
        <span class="meta-pill"><?= e($currentModerator['bond']) ?></span>
        <a class="avatar" href="/profile"><?= e($currentModerator['initials']) ?></a>
    </div>
</nav>
