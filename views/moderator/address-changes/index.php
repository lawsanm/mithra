<?php

declare(strict_types=1);

/**
 * Address changes waiting in the moderator's division (Plan §18.1): compare
 * the proof with the new address, then approve or reject with a reason.
 *
 * @var array $changes rows: id, name, initials, from, to, sent, proof
 * @var array $errors  per-field messages, with 'for' naming the request they belong to
 * @var array|null $flash
 */

$changes = $changes ?? [];
$errors  = $errors ?? [];

$pageTitle = 'Address changes';
$navActive = 'verifications';

$chrome = 'moderator';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Address changes</h1>
</header>

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<?php if (isset($errors['form'])): ?>
    <p class="notice notice--error" role="alert"><?= e($errors['form']) ?></p>
<?php endif; ?>

<?php if ($changes === []): ?>
    <div class="empty-state">
        <p class="empty-state__title">No address changes waiting</p>
        <p class="empty-state__body">When a member in your division moves, their request appears here.</p>
        <a class="btn btn--ghost" href="<?= base_url() ?>/moderator/verifications">Back to verifications</a>
    </div>
<?php else: ?>
    <ul class="row-list">
        <?php foreach ($changes as $change): ?>
            <?php $mine = ($errors['for'] ?? '') === (string) $change['id']; ?>
            <li class="panel panel--wide">
                <div class="list-row">
                    <span class="avatar"><?= e($change['initials']) ?></span>
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($change['name']) ?></span>
                        <span class="list-row__meta">From: <?= e($change['from']) ?></span>
                        <span class="list-row__meta">To: <?= e($change['to']) ?>  ·  sent <?= e($change['sent']) ?></span>
                    </div>
                    <a class="link" href="<?= e($change['proof']) ?>">
                        <img class="thumb thumb--sm thumb__img" src="<?= e($change['proof']) ?>" alt="Proof of address for <?= e($change['name']) ?>">
                    </a>
                </div>

                <div class="actions">
                    <form method="post" action="<?= base_url() ?>/moderator/address-changes/<?= e((string) $change['id']) ?>/approve">
                        <?= csrf_field() ?>
                        <button class="btn btn--primary" type="submit">Approve new address</button>
                    </form>

                    <form class="stack" method="post" action="<?= base_url() ?>/moderator/address-changes/<?= e((string) $change['id']) ?>/reject">
                        <?= csrf_field() ?>
                        <label class="field__label" for="reason-<?= e((string) $change['id']) ?>">Reason, if rejecting</label>
                        <input class="input" type="text" id="reason-<?= e((string) $change['id']) ?>" name="reason" maxlength="255"
                            <?= $mine && isset($errors['reason']) ? 'aria-invalid="true"' : '' ?>>
                        <?php if ($mine && isset($errors['reason'])): ?>
                            <span class="field__error"><?= e($errors['reason']) ?></span>
                        <?php endif; ?>
                        <button class="btn btn--ghost" type="submit">Reject</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
