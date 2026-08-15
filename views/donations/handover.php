<?php

declare(strict_types=1);

/**
 * Confirm a donation handover. Figma: "Donation — Handover Confirm" (74:155).
 *
 * @var array $donation  item, meta
 * @var array $recipient initials, name, meta
 * @var array $badge     donor badge line
 */

// Sample view data — replaced by the controller once DonationController lands.
$donation ??= [
    'item' => 'Baby Clothes Bundle',
    'meta' => 'Donation  ·  declared 80 pts  ·  listed 8 Jul',
];

$recipient ??= [
    'initials' => 'JK',
    'name'     => 'Recipient: J. Kavipriya',
    'meta'     => 'Trust 98  ·  8 transactions  ·  chosen 16 Jul',
];

$badge ??= 'Donor  ·  will become 4 items given';

$pageTitle = 'Confirm donation handover';
$navActive = 'items';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="detail__title">Confirm donation handover</h1>

<form class="panel panel--wide" method="post" action="<?= base_url() ?>/donations/1/handover">
    <?= csrf_field() ?>

    <div class="media">
        <span class="thumb thumb--sm"></span>
        <span class="media__body">
            <span class="media__title"><?= e($donation['item']) ?></span>
            <span class="media__meta"><?= e($donation['meta']) ?></span>
        </span>
    </div>

    <hr class="divider">

    <div class="media">
        <span class="avatar avatar--md"><?= e($recipient['initials']) ?></span>
        <span class="media__body">
            <span class="media__title media__title--sm"><?= e($recipient['name']) ?></span>
            <span class="media__meta"><?= e($recipient['meta']) ?></span>
        </span>
    </div>

    <p class="notice notice--info">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        Confirming marks the item as donated and closes the listing. No points change
        hands — donations are free. Your Donor badge on your profile updates automatically.
    </p>

    <p class="award-pill">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-award"></use></svg>
        <?= e($badge) ?>
    </p>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/donations/1">Back</a>
        <button class="btn btn--primary" type="submit">Confirm handover</button>
    </div>
</form>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
