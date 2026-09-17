<?php

declare(strict_types=1);

/**
 * The avatar's account menu. A native <details> keeps it usable without
 * JavaScript. Included by partials/nav.php.
 *
 * @var string     $chrome member, moderator, admin, sponsor-liaison or sponsor
 * @var array|null $viewer initials of the signed-in account
 */

$memberId = (int) ($_SESSION['user_id'] ?? 0);
$accountLinks = match ($chrome) {
    'admin' => [
        ['Profile', '/admin/settings/profile'], ['Security', '/admin/settings/security'],
        ['Reset codes', '/admin/reset-codes'],
        ['Notifications', '/admin/notifications'], ['Notification preferences', '/admin/settings/notifications'],
        ['Cron Jobs', '/admin/cron'], ['Point Policies', '/admin/pools/policies'],
        ['Disaster Mode', '/admin/disaster'],
    ],
    'sponsor' => [
        ['Dashboard', '/sponsor/dashboard'], ['Purchase Points', '/sponsor/purchase-points'],
        ['CSR Reports', '/sponsor/csr-reports'], ['Branding', '/sponsor/branding'],
        ['Notifications', '/sponsor/notifications'], ['Change password', '/account/password'],
    ],
    'sponsor-liaison' => [
        ['Dashboard', '/sponsor-liaison/dashboard'], ['Sponsors', '/sponsor-liaison/sponsors'],
        ['Record contribution', '/sponsor-liaison/purchases/create'],
        ['Aid Grants', '/sponsor-liaison/aid-grants'], ['CSR Reports', '/sponsor-liaison/csr-reports'],
        ['Change password', '/account/password'],
    ],
    'moderator' => [
        ['Dashboard', '/moderator/dashboard'], ['Verifications', '/moderator/verifications'],
        ['Approvals', '/moderator/listing-approvals'], ['Cases', '/moderator/cases'],
        ['Aid Vouching', '/moderator/aid-vouching'], ['Disasters', '/moderator/disasters'],
        ['Address changes', '/moderator/address-changes'], ['Reset codes', '/moderator/reset-codes'],
        ['Member Profile', '/profile'], ['Settings', '/settings'],
        ['Help', '/help'],
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
    <summary class="avatar account-menu__trigger" aria-label="Open account navigation"><?= e($viewer['initials'] ?? '') ?></summary>
    <div class="account-menu__panel">
        <ul>
            <?php foreach ($accountLinks as [$label, $path]): ?>
                <li><a class="account-menu__link" href="<?= e(base_url() . $path) ?>"><?= e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="account-menu__footer">
            <form method="post" action="<?= base_url() ?>/logout" novalidate>
                <?= csrf_field() ?>
                <button class="account-menu__link account-menu__logout" type="submit">Log out</button>
            </form>
        </div>
    </div>
</details>
