<?php

declare(strict_types=1);

/**
 * My profile — view and edit. Figma: "My Profile — View / Edit" (94:223).
 *
 * @var array $member initials, name, verified, donor badge, meta
 * @var array $draft  editable field values
 * @var array $errors per-field messages from the Validator
 */

// Sample view data — replaced by the controller once ProfileController lands.
$member ??= [
    'initials' => 'ML',
    'name'     => 'M. Lawsan',
    'verified' => true,
    'donor'    => 'Donor · 3 items given',
    'meta'     => 'Kollupitiya GN Division  ·  member since Mar 2025  ·  trust score 80 / 100',
    // Own public profile lives on the same /members/{id} route as everyone else's.
    'public_href' => base_url() . '/members/12',
];

$draft ??= [
    'display_name' => 'M. Lawsan',
    'mobile'       => '+94 77 123 4567',
    'email'        => 'lawsan@email.com',
    'address'      => '24/3 Galle Road, Colombo 03',
];

$errors ??= [];

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

<form class="panel panel--wide" method="post" action="<?= base_url() ?>/profile" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <h2 class="panel__heading">Edit details</h2>

    <div class="field">
        <label class="field__label" for="display-name">Display name</label>
        <input class="input" type="text" id="display-name" name="display_name" value="<?= e($draft['display_name']) ?>" required>
        <?php if (isset($errors['display_name'])): ?>
            <span class="field__error"><?= e($errors['display_name']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="mobile">Mobile number</label>
        <input class="input" type="tel" id="mobile" name="mobile" value="<?= e($draft['mobile']) ?>" required>
        <?php if (isset($errors['mobile'])): ?>
            <span class="field__error"><?= e($errors['mobile']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="<?= e($draft['email']) ?>" required>
        <?php if (isset($errors['email'])): ?>
            <span class="field__error"><?= e($errors['email']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="address">Address</label>
        <input class="input" type="text" id="address" name="address" value="<?= e($draft['address']) ?>" required>

        <label class="upload-inline">
            <span aria-hidden="true">⬆</span>
            <span class="upload-inline__label">Upload proof of address</span>
            <span class="upload-inline__hint">· utility bill or GN certificate (PDF/JPG, max 5 MB)</span>
            <input class="visually-hidden" type="file" name="address_proof" accept="image/jpeg,application/pdf">
        </label>

        <span class="field__hint">
            Changing your address requires proof of residence and re-verification by your moderator.
        </span>
    </div>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/dashboard">Cancel</a>
        <button class="btn btn--primary" type="submit">Save changes</button>
    </div>
</form>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
