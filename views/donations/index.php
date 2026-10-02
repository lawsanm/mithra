<?php

declare(strict_types=1);

/**
 * Donation requests received for one of my donation listings.
 * Figma: "Donations — Requests Received" (74:92).
 *
 * @var array $donation id, item, status, status_label, request_count, first_come, open, cancellable, has_recipient
 * @var array $requests rows: id, initials, name, meta, status, message, profile_href, choosable
 * @var array|null $flash
 */

$pageTitle = 'Donation requests';
$navActive = 'items';

include __DIR__ . '/../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/items?type=donations">My Items</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($donation['item']) ?></span>
</nav>

<header class="record-head">
    <h1 class="record-head__title">Donation requests — <?= e($donation['item']) ?></h1>
    <span class="badge badge--info">
        <span aria-hidden="true">i</span>
        <?= e($donation['request_count']) ?>
    </span>
</header>

<p class="record-meta"><?= e($donation['status_label']) ?></p>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<?php if ($donation['open']): ?>
    <form class="toggle-field" method="post" action="<?= base_url() ?>/donations/<?= e((string) $donation['id']) ?>/mode" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="first_come" value="<?= $donation['first_come'] ? '0' : '1' ?>">
        <span class="toggle-field__label">
            First-come-first-served — <?= $donation['first_come'] ? 'on. The first request is chosen automatically.' : 'off. You choose the recipient.' ?>
        </span>
        <button class="btn btn--ghost" type="submit"><?= $donation['first_come'] ? 'Turn off' : 'Turn on' ?></button>
    </form>
<?php endif; ?>

<?php if ($donation['has_recipient']): ?>
    <div class="actions">
        <a class="btn btn--primary" href="<?= base_url() ?>/donations/<?= e((string) $donation['id']) ?>/handover">Go to handover</a>
    </div>
<?php endif; ?>

<?php if ($requests === []): ?>
    <div class="empty-state">
        <p class="empty-state__title">No requests yet</p>
        <p class="empty-state__body">Members of your division see this donation on Browse and can ask for it.</p>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($requests as $request): ?>
            <li class="record-card record-card--roomy">
                <span class="avatar avatar--md"><?= e($request['initials']) ?></span>
                <div class="record-card__body">
                    <span class="record-card__party"><?= e($request['name']) ?></span>
                    <span class="record-card__terms"><?= e($request['meta']) ?> · <?= e(ucfirst($request['status'])) ?></span>
                    <?php if ($request['message'] !== ''): ?>
                        <p class="record-card__quote"><?= e($request['message']) ?></p>
                    <?php endif; ?>
                </div>
                <a class="btn btn--ghost" href="<?= e($request['profile_href']) ?>">View profile</a>
                <?php if ($request['choosable']): ?>
                    <form method="post" action="<?= base_url() ?>/donations/<?= e((string) $donation['id']) ?>/select"
                        data-confirm="Give it to <?= e($request['name']) ?>? Everyone else who asked is told it has gone." novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="request_id" value="<?= e((string) $request['id']) ?>">
                        <button class="btn btn--primary" type="submit">Choose recipient</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($donation['cancellable']): ?>
    <form method="post" action="<?= base_url() ?>/donations/<?= e((string) $donation['id']) ?>/cancel"
        data-confirm="Stop giving this item away? Open requests are declined and the listing is removed." novalidate>
        <?= csrf_field() ?>
        <button class="btn btn--ghost" type="submit">Cancel donation</button>
    </form>
<?php endif; ?>

<?php
$pageScripts = ['confirm.js'];
include __DIR__ . '/../../partials/footer.php';
?>
