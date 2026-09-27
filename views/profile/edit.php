<?php

declare(strict_types=1);

/**
 * My profile — view and edit. Figma: "My Profile — View / Edit" (94:223).
 *
 * Two forms: contact details (saved at once) and a new home address, which
 * waits with its proof for the division moderator (Plan §18.1).
 *
 * @var array       $member         initials, name, verified, donor badge, meta
 * @var array       $draft          full_name, phone, email, address as shown in the forms
 * @var string      $currentAddress the verified address on file
 * @var array|null  $addressChange  the latest address-change request, if any
 * @var array       $errors         per-field messages from the last save
 * @var array|null  $flash
 */

$errors        = $errors ?? [];
$addressChange = $addressChange ?? null;

$pageTitle = 'My profile';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<section class="panel">
    <h1 class="visually-hidden">My profile</h1>
    <div class="profile-head">
        <span class="avatar avatar--xl"><?= e($member['initials']) ?></span>
        <div class="profile-head__body">
            <div class="profile-head__name-row">
                <span class="profile-head__name"><?= e($member['name']) ?></span>
                <?php if ($member['verified']): ?>
                    <span class="badge badge--success">
                        <span aria-hidden="true">✓</span>
                        Verified
                    </span>
                <?php endif; ?>
                <span class="award-pill award-pill--sm">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-award"></use></svg>
                    <?= e($member['donor']) ?>
                </span>
            </div>
            <span class="profile-head__meta"><?= e($member['meta']) ?></span>
        </div>
        <a class="btn btn--ghost" href="<?= e($member['public_href']) ?>">View public profile</a>
    </div>
</section>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<form class="panel panel--wide" method="post" action="<?= base_url() ?>/profile" novalidate>
    <?= csrf_field() ?>

    <h2 class="panel__heading">Edit details</h2>

    <div class="field">
        <label class="field__label" for="full-name">Name</label>
        <input class="input" type="text" id="full-name" name="full_name" value="<?= e($draft['full_name']) ?>" autocomplete="name"
            <?= isset($errors['full_name']) ? 'aria-invalid="true"' : '' ?>>
        <?= field_error($errors, 'full_name') ?>
    </div>

    <div class="field">
        <label class="field__label" for="mobile">Mobile number</label>
        <input class="input" type="tel" id="mobile" name="phone" value="<?= e($draft['phone']) ?>" autocomplete="tel"
            <?= isset($errors['phone']) ? 'aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['phone'])): ?>
            <span class="field__error"><?= e($errors['phone']) ?></span>
        <?php else: ?>
            <span class="field__hint">You can sign in with this number.</span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="<?= e($draft['email']) ?>" autocomplete="email"
            <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['email'])): ?>
            <span class="field__error"><?= e($errors['email']) ?></span>
        <?php else: ?>
            <span class="field__hint">Password-reset links are sent here.</span>
        <?php endif; ?>
    </div>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/dashboard">Cancel</a>
        <button class="btn btn--primary" type="submit">Save changes</button>
    </div>
</form>

<form class="panel panel--wide" method="post" action="<?= base_url() ?>/profile/address" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <h2 class="panel__heading">Home address</h2>

    <p class="record-meta">Verified address: <?= e($currentAddress ?? '') ?></p>

    <?php if ($addressChange !== null && $addressChange['status'] === 'pending'): ?>
        <p class="notice notice--info">
            Waiting for your moderator: “<?= e((string) $addressChange['new_address']) ?>”, sent
            <?= e(date('j M Y', strtotime((string) $addressChange['created_at']))) ?>. Sending another
            request replaces this one.
        </p>
    <?php elseif ($addressChange !== null && $addressChange['status'] === 'rejected'): ?>
        <p class="notice notice--error">
            Your moderator could not accept “<?= e((string) $addressChange['new_address']) ?>”:
            <?= e((string) ($addressChange['reason'] ?? '')) ?>
        </p>
    <?php endif; ?>

    <div class="field">
        <label class="field__label" for="address">New address</label>
        <input class="input" type="text" id="address" name="address" value="<?= e($draft['address']) ?>" autocomplete="street-address"
            <?= isset($errors['address']) ? 'aria-invalid="true"' : '' ?>>
        <?= field_error($errors, 'address') ?>
    </div>

    <div class="field">
        <label class="field__label" for="address-proof">Proof of the new address</label>
        <input class="input" type="file" id="address-proof" name="address_proof" accept="image/jpeg,image/png,image/webp"
            <?= isset($errors['address_proof']) ? 'aria-invalid="true"' : '' ?>>
        <?php if (isset($errors['address_proof'])): ?>
            <span class="field__error"><?= e($errors['address_proof']) ?></span>
        <?php else: ?>
            <span class="field__hint">
                A photo of a utility bill or GN certificate (JPG, PNG or WebP, up to 5 MB). Only your
                moderator and the Admin can see it.
            </span>
        <?php endif; ?>
    </div>

    <button class="btn btn--primary" type="submit">Send for verification</button>
</form>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
