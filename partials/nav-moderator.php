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
    'dashboard'         => ['label' => 'Dashboard',     'href' => base_url() . '/moderator/dashboard'],
    'verifications'     => ['label' => 'Verifications', 'href' => base_url() . '/moderator/verifications'],
    'listing-approvals' => ['label' => 'Approvals',     'href' => base_url() . '/moderator/listing-approvals'],
    'cases'             => ['label' => 'Cases',         'href' => base_url() . '/moderator/cases'],
    'disasters'         => ['label' => 'Disasters',     'href' => base_url() . '/moderator/disasters'],
];

?>
<nav class="nav" aria-label="Moderator">
    <a class="nav__brand" href="<?= base_url() ?>/moderator/dashboard">
        <img width="21" height="28" class="nav__logo" src="<?= base_url() ?>/img/logo-mark.svg" alt="">
        <span class="nav__wordmark">Mithra</span>
        <span class="nav__tagline">Lend · Share · Care</span>
    </a>

    <span class="nav__role-badge">Moderator</span>

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
        <a class="nav__bell" href="<?= base_url() ?>/notifications?context=moderator" aria-label="Notifications">
            <img class="nav__bell-icon" width="24" height="24" src="<?= base_url() ?>/img/nav-bell.svg" alt="">
        </a>
        <span class="meta-pill"><?= e($currentModerator['bond']) ?></span>
        <?php $accountRole = 'moderator'; $accountInitials = $currentModerator['initials']; include __DIR__ . '/account-menu.php'; ?>
    </div>
</nav>
