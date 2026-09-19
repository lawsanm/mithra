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

    <ul class="nav__items">
        <li><a class="nav__link" href="<?= base_url() ?>/transparency">Transparency</a></li>
        <li><a class="nav__link" href="<?= base_url() ?>/help">Help</a></li>
    </ul>

    <span class="nav__spacer"></span>

    <div class="nav__actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/login"<?= $navActive === 'login' ? ' aria-current="page"' : '' ?>>Log in</a>
        <button class="btn btn--primary" type="button" disabled>Register</button>
    </div>
</nav>
