<?php

declare(strict_types=1);

/**
 * Top navigation bar — the Figma Nav Bar component (68px tall, 16px/48px
 * padding, 1px bottom border). Every role shares the layout; each chrome
 * differs only in its home link, badge, links and right-hand pills.
 *
 * @var string     $chrome    member, moderator, admin, sponsor-liaison, sponsor or public
 * @var string     $navActive key of the current page's link
 * @var array|null $viewer    the signed-in account: initials, points, bond, company
 */

$viewer = $viewer ?? null;

$bars = [
    'member' => [
        'label' => 'Main', 'home' => '/dashboard', 'badge' => null, 'bell' => '/notifications',
        'links' => [
            'dashboard' => ['Dashboard', '/dashboard'],
            'browse'    => ['Browse Items', '/items/browse'],
            'items'     => ['My Items', '/items'],
            'bookings'  => ['My Bookings', '/bookings'],
            'wallet'    => ['Wallet', '/wallet'],
            'gifts'     => ['Gifts', '/gifts'],
        ],
    ],
    'moderator' => [
        'label' => 'Moderator', 'home' => '/moderator/dashboard', 'badge' => 'Moderator', 'bell' => '/notifications',
        'links' => [
            'dashboard'         => ['Dashboard', '/moderator/dashboard'],
            'verifications'     => ['Verifications', '/moderator/verifications'],
            'listing-approvals' => ['Approvals', '/moderator/listing-approvals'],
            'cases'             => ['Cases', '/moderator/cases'],
            'disasters'         => ['Disasters', '/moderator/disasters'],
        ],
    ],
    'admin' => [
        'label' => 'Admin', 'home' => '/admin/dashboard', 'badge' => 'Admin', 'bell' => '/admin/notifications',
        'links' => [
            'dashboard'         => ['Dashboard', '/admin/dashboard'],
            'divisions'         => ['Divisions', '/admin/divisions'],
            'moderators'        => ['Moderators', '/admin/moderators'],
            'disputes'          => ['Disputes', '/admin/disputes'],
            'listing-approvals' => ['Listings', '/admin/listing-approvals'],
            'categories'        => ['Categories', '/admin/categories'],
            'pools'             => ['Pools', '/admin/pools'],
            'ledger'            => ['Ledger', '/admin/ledger'],
            'users'             => ['Users', '/admin/users'],
        ],
    ],
    'sponsor-liaison' => [
        'label' => 'Sponsor Liaison', 'home' => '/sponsor-liaison/dashboard', 'badge' => 'Liaison', 'bell' => null,
        'links' => [
            'dashboard'   => ['Dashboard', '/sponsor-liaison/dashboard'],
            'sponsors'    => ['Sponsors', '/sponsor-liaison/sponsors'],
            'purchases'   => ['Purchases', '/sponsor-liaison/purchases'],
            'points-pool' => ['Points Pool', '/sponsor-liaison/points-pool'],
            'disasters'   => ['Disasters', '/sponsor-liaison/disasters'],
            'aid-grants'  => ['Aid Grants', '/sponsor-liaison/aid-grants'],
            'csr-reports' => ['CSR Reports', '/sponsor-liaison/csr-reports'],
        ],
    ],
    'sponsor' => [
        'label' => 'Sponsor', 'home' => '/sponsor/dashboard', 'badge' => 'Sponsor', 'bell' => null,
        'links' => [
            'dashboard'       => ['Dashboard', '/sponsor/dashboard'],
            'purchase-points' => ['Purchase Points', '/sponsor/purchase-points'],
            'csr-reports'     => ['CSR Reports', '/sponsor/csr-reports'],
            'branding'        => ['Branding', '/sponsor/branding'],
            'notifications'   => ['Notifications', '/sponsor/notifications'],
        ],
    ],
];

$bar = $bars[$chrome] ?? null;

?>
<nav class="nav" aria-label="<?= e($bar['label'] ?? 'Main') ?>">
    <a class="nav__brand" href="<?= e(base_url() . ($bar['home'] ?? '/')) ?>">
        <img width="21" height="28" class="nav__logo" src="<?= base_url() ?>/img/logo-mark.svg" alt="">
        <span class="nav__wordmark">Mithra</span>
        <span class="nav__tagline">Lend · Share · Care</span>
    </a>

<?php if ($bar === null): ?>
    <ul class="nav__items">
        <?php foreach (['how-it-works' => ['How It Works', '/how-it-works'], 'transparency' => ['Transparency', '/transparency'], 'help' => ['Help', '/help']] as $key => [$label, $href]): ?>
            <li>
                <a class="nav__link<?= $navActive === $key ? ' nav__link--active' : '' ?>" href="<?= e(base_url() . $href) ?>"<?= $navActive === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <span class="nav__spacer"></span>

    <div class="nav__actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/login"<?= $navActive === 'login' ? ' aria-current="page"' : '' ?>>Log in</a>
        <a class="btn btn--primary" href="<?= base_url() ?>/register"<?= $navActive === 'register' ? ' aria-current="page"' : '' ?>>Register</a>
    </div>
<?php else: ?>
    <?php if ($bar['badge'] !== null): ?>
        <span class="nav__role-badge"><?= e($bar['badge']) ?></span>
    <?php endif; ?>

    <button class="nav__toggle btn btn--ghost" type="button" data-nav-toggle aria-controls="primary-links" aria-expanded="false" hidden>Menu</button>
    <ul class="nav__items" id="primary-links">
        <?php foreach ($bar['links'] as $key => [$label, $path]): ?>
            <li>
                <a
                    class="nav__link<?= $key === $navActive ? ' nav__link--active' : '' ?>"
                    href="<?= e(base_url() . $path) ?>"
                    <?= $key === $navActive ? 'aria-current="page"' : '' ?>
                ><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="nav__spacer"></div>

    <div class="nav__actions">
        <?php if ($bar['bell'] !== null): ?>
            <?php $unread = (int) ($viewer['unread'] ?? 0); ?>
            <a class="nav__bell" href="<?= e(base_url() . $bar['bell']) ?>" aria-label="Notifications<?= $unread > 0 ? ', ' . e((string) $unread) . ' unread' : '' ?>">
                <img class="nav__bell-icon" width="24" height="24" src="<?= base_url() ?>/img/nav-bell.svg" alt="">
                <?php if ($bar['bell'] === '/notifications'): ?>
                    <span class="nav__bell-count" data-unread-poll="<?= e(base_url() . '/notifications/unread-count') ?>"<?= $unread === 0 ? ' hidden' : '' ?>><?= e($unread > 99 ? '99+' : (string) $unread) ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        <?php if ($viewer !== null && $chrome === 'member'): ?>
            <a class="meta-pill" href="<?= base_url() ?>/wallet"><?= e($viewer['points']) ?></a>
        <?php elseif ($viewer !== null && $chrome === 'moderator'): ?>
            <span class="meta-pill"><?= e($viewer['bond']) ?></span>
        <?php endif; ?>
        <?php include __DIR__ . '/account-menu.php'; ?>
        <?php if ($viewer !== null && $chrome === 'sponsor'): ?>
            <span class="nav__company"><?= e($viewer['company']) ?></span>
        <?php endif; ?>
    </div>
<?php endif; ?>
</nav>
