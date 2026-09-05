<?php

declare(strict_types=1);

/** Native disclosure keeps account navigation usable without JavaScript. */
$accountRole = $accountRole ?? 'member';
$accountInitials = $accountInitials ?? '';
$memberId = (int) ($_SESSION['user_id'] ?? Config::get('demo_member_id', 4));
$accountLinks = match ($accountRole) {
    'admin' => [
        ['Profile', '/admin/settings/profile'], ['Security', '/admin/settings/security'],
        ['Notifications', '/admin/notifications'], ['Notification preferences', '/admin/settings/notifications'],
        ['Cron Jobs', '/admin/cron'], ['Point Policies', '/admin/pools/policies'],
        ['Disaster Mode', '/admin/disaster'],
    ],
    'sponsor' => [
        ['Dashboard', '/sponsor/dashboard'], ['Purchase Points', '/sponsor/purchase-points'],
        ['CSR Reports', '/sponsor/csr-reports'], ['Branding', '/sponsor/branding'],
        ['Notifications', '/sponsor/notifications'],
    ],
    'sponsor-liaison' => [
        ['Dashboard', '/sponsor-liaison/dashboard'], ['Sponsors', '/sponsor-liaison/sponsors'],
        ['Record contribution', '/sponsor-liaison/purchases/create'],
        ['Aid Grants', '/sponsor-liaison/aid-grants'], ['CSR Reports', '/sponsor-liaison/csr-reports'],
    ],
    'moderator' => [
        ['Dashboard', '/moderator/dashboard'], ['Verifications', '/moderator/verifications'],
        ['Approvals', '/moderator/listing-approvals'], ['Cases', '/moderator/cases'],
        ['Aid Vouching', '/moderator/aid-vouching'], ['Disasters', '/moderator/disasters'],
        ['Member Profile', '/profile?context=moderator'], ['Help', '/help?context=moderator'],
    ],
    default => [
        ['Profile', '/profile'], ['Public Profile', '/members/' . $memberId],
        ['Trust Score', '/trust'], ['Ratings', '/ratings'], ['Wallet', '/wallet'],
        ['Gifting History', '/gifts'], ['Donations', '/donations/1'],
        ['Aid Grants', '/aid-grants'], ['Community', '/community/temporary'],
        ['Notifications', '/notifications'], ['Settings', '/settings'],
        ['Transparency', '/transparency'], ['Help', '/help'],
    ],
};
?>
<details class="account-menu">
    <summary class="avatar account-menu__trigger" aria-label="Open <?= e($accountRole) ?> account navigation"><?= e($accountInitials) ?></summary>
    <div class="account-menu__panel">
        <ul>
            <?php foreach ($accountLinks as [$label, $path]): ?>
                <li><a class="account-menu__link" href="<?= e(base_url() . $path) ?>"><?= e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="account-menu__footer">
            <button class="account-menu__link account-menu__logout" type="button" disabled>Log out</button>
            <p class="account-menu__note">Demo session — sign-in is not enabled.</p>
        </div>
    </div>
</details>
