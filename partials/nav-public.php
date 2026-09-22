<?php

declare(strict_types=1);

/**
 * Top bar for the signed-out screens — Figma "Login" (93:283). Same 68px bar as
 * the member nav, with the public destinations instead of the account menu.
 *
 * The bar in Figma also carries "How It Works", which belongs to the About
 * screen (92:178). That screen is not routed yet, so the link is left out
 * rather than pointed somewhere else — add it here when About ships.
 *
 * Transparency and Help sat here too. Both render a member's chrome and need a
 * session, and AuthMiddleware now sends a signed-out visitor to /login, so
 * listing them here would be a link that goes somewhere else. They are in the
 * signed-in navigation; put them back when a signed-out version of each exists.
 *
 * @var string $navActive route key of the current page
 */

$navActive = $navActive ?? '';

?>
<nav class="nav" aria-label="Main">
    <a class="nav__brand" href="<?= base_url() ?>/">
        <img width="21" height="28" class="nav__logo" src="<?= base_url() ?>/img/logo-mark.svg" alt="">
        <span class="nav__wordmark">Mithra</span>
        <span class="nav__tagline">Lend · Share · Care</span>
    </a>

    <span class="nav__spacer"></span>

    <div class="nav__actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/login"<?= $navActive === 'login' ? ' aria-current="page"' : '' ?>>Log in</a>
        <a class="btn btn--primary" href="<?= base_url() ?>/register"<?= $navActive === 'register' ? ' aria-current="page"' : '' ?>>Register</a>
    </div>
</nav>
