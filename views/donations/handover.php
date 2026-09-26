<?php

declare(strict_types=1);

/**
 * Confirm a donation handover. Figma: "Donation — Handover Confirm" (74:155).
 *
 * @var array $donation  item, meta
 * @var array $recipient initials, name, meta
 * @var array $badge     donor badge line
 */

$pageTitle = 'Confirm donation handover';
$navActive = 'items';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="detail__title">Confirm donation handover</h1>

<div class="panel panel--wide" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
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
        <a class="btn btn--ghost" href="<?= base_url() ?>/donations/<?= e((string) $donation['id']) ?>">Back</a>
        <button class="btn btn--primary" type="submit" disabled>Confirm handover</button>
    </div>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
