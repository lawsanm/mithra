<?php

declare(strict_types=1);

/**
 * Confirm a donation handover. Figma: "Donation — Handover Confirm" (74:155).
 * Both the donor and the recipient confirm; the second confirmation completes
 * the donation (Plan §13.1 step 4).
 *
 * @var array  $donation  id, item, photo, meta, is_donor
 * @var array  $recipient initials, name, meta
 * @var array  $state     waiting, completed, mine, theirs
 * @var string $badge     donor badge line, empty for the recipient
 * @var array|null $flash
 */

$pageTitle = 'Confirm donation handover';
$navActive = 'items';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="detail__title">Confirm donation handover</h1>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<div class="panel panel--wide">
    <div class="media">
        <?php $photoUrl = $donation['photo']; $photoTitle = $donation['item']; $photoClass = 'thumb--sm'; include __DIR__ . '/../../partials/item-photo.php'; ?>
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

    <p class="line-item">
        <span class="line-item__label"><?= $donation['is_donor'] ? 'You confirmed the transfer' : 'You confirmed receipt' ?></span>
        <span class="badge badge--<?= $state['mine'] ? 'success' : 'neutral' ?>"><?= $state['mine'] ? 'Yes' : 'Not yet' ?></span>
    </p>
    <p class="line-item">
        <span class="line-item__label"><?= $donation['is_donor'] ? 'Recipient confirmed receipt' : 'Donor confirmed the transfer' ?></span>
        <span class="badge badge--<?= $state['theirs'] ? 'success' : 'neutral' ?>"><?= $state['theirs'] ? 'Yes' : 'Not yet' ?></span>
    </p>

    <?php if ($state['completed']): ?>
        <p class="notice notice--success">This donation is complete. You can rate each other from Ratings.</p>
    <?php else: ?>
        <p class="notice notice--info">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            Confirm once the item has changed hands. When both of you have confirmed, the item is
            marked as donated and the listing closes. No points change hands — donations are free.
        </p>
    <?php endif; ?>

    <?php if ($badge !== ''): ?>
        <p class="award-pill">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-award"></use></svg>
            <?= e($badge) ?>
        </p>
    <?php endif; ?>

    <div class="actions">
        <?php if ($donation['is_donor']): ?>
            <a class="btn btn--ghost" href="<?= base_url() ?>/donations/<?= e((string) $donation['id']) ?>">Back to requests</a>
        <?php else: ?>
            <a class="btn btn--ghost" href="<?= base_url() ?>/dashboard">Back</a>
        <?php endif; ?>
        <?php if ($state['completed']): ?>
            <a class="btn btn--primary" href="<?= base_url() ?>/ratings">Rate each other</a>
        <?php elseif ($state['waiting'] && !$state['mine']): ?>
            <form method="post" action="<?= base_url() ?>/donations/<?= e((string) $donation['id']) ?>/confirm" novalidate>
                <?= csrf_field() ?>
                <button class="btn btn--primary" type="submit"><?= $donation['is_donor'] ? 'Confirm handover' : 'Confirm I received it' ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
