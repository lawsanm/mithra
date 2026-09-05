<?php

declare(strict_types=1);

/**
 * Edit one listing. The create flow is a four-step wizard because the member is
 * deciding; editing is a single page because they already decided and want to
 * change one thing.
 *
 * @var array $item       id, status, badge, badge_glyph, badge_label, editable,
 *                        can_pause, can_resume
 * @var array $categories rows from item_categories: id, name
 * @var array $photos     rows: path, url
 * @var array $draft      current field values
 * @var array $errors     per-field messages
 * @var array|null $flash
 */

$item       = $item ?? [];
$categories = $categories ?? [];
$photos     = $photos ?? [];
$draft      = $draft ?? [];
$errors     = $errors ?? [];

$isDonation = ($draft['listing_type'] ?? 'rental') === 'donation';

$pageTitle = 'Edit listing';
$navActive = 'items';

include __DIR__ . '/../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/items">My Items</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e((string) $draft['name']) ?></span>
</nav>

<header class="page-header">
    <h1 class="page-header__title">Edit listing</h1>
    <span class="badge badge--<?= e($item['badge']) ?>">
        <span aria-hidden="true"><?= e($item['badge_glyph']) ?></span>
        <?= e($item['badge_label']) ?>
    </span>
</header>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<?php if (!$item['editable']): ?>

    <p class="notice notice--warning">
        <?= $item['status'] === 'borrowed'
            ? 'This item is out on loan. You can edit the listing once it is back.'
            : 'This listing has been removed and can no longer be edited.' ?>
    </p>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/items">Back to My Items</a>
    </div>

<?php else: ?>

    <p class="notice notice--info">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        Changing the name, description, category, photos, declared value or listing type
        sends the listing back to your moderator for approval. Changing only the rate does not.
    </p>

    <form class="form-card" method="post" action="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="field">
            <label class="field__label" for="item-name">Item name</label>
            <input
                class="input"
                type="text"
                id="item-name"
                name="name"
                value="<?= e((string) $draft['name']) ?>"
                maxlength="150"
                required
            >
            <?php if (isset($errors['name'])): ?>
                <span class="field__error"><?= e($errors['name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="item-category">Category</label>
            <select class="input" id="item-category" name="category" required>
                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= e((string) $category['id']) ?>"
                        <?= (string) $draft['category'] === (string) $category['id'] ? 'selected' : '' ?>
                    ><?= e((string) $category['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['category'])): ?>
                <span class="field__error"><?= e($errors['category']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="item-description">Description — optional</label>
            <textarea
                class="input"
                id="item-description"
                name="description"
                rows="4"
                maxlength="2000"
            ><?= e((string) $draft['description']) ?></textarea>
            <?php if (isset($errors['description'])): ?>
                <span class="field__error"><?= e($errors['description']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="declared-value">Declared value (pts)</label>
            <input
                class="input input--narrow"
                type="number"
                id="declared-value"
                name="declared_value"
                value="<?= e((string) $draft['declared_value']) ?>"
                min="1"
                step="1"
                required
            >
            <?php if (isset($errors['declared_value'])): ?>
                <span class="field__error"><?= e($errors['declared_value']) ?></span>
            <?php endif; ?>
        </div>

        <p class="form-card__legend">Photos</p>

        <div class="field-row">
            <?php foreach ($photos as $photo): ?>
                <div class="field">
                    <img class="thumb thumb--sm thumb__img" src="<?= e($photo['url']) ?>" alt="">
                    <label class="field__hint">
                        <input type="checkbox" name="keep_photos[]" value="<?= e($photo['path']) ?>" checked>
                        Keep
                    </label>
                </div>
            <?php endforeach; ?>
            <label class="upload-tile">
                <span aria-hidden="true">＋</span>
                <span class="visually-hidden">Add a photo</span>
                <input class="visually-hidden" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
            </label>
        </div>

        <p class="field__hint">
            Clear a "Keep" box to drop that photo when you save. A listing needs at least one.
        </p>
        <?php if (isset($errors['photos'])): ?>
            <span class="field__error"><?= e($errors['photos']) ?></span>
        <?php endif; ?>

        <p class="form-card__legend">Listing type</p>

        <label class="choice">
            <input class="choice__input" type="radio" name="listing_type" value="rental" <?= !$isDonation ? 'checked' : '' ?>>
            <span class="choice__body">
                <span class="choice__title">Rental</span>
                <span class="choice__note">Lend for points per day or month.</span>
            </span>
        </label>

        <label class="choice">
            <input class="choice__input" type="radio" name="listing_type" value="donation" <?= $isDonation ? 'checked' : '' ?>>
            <span class="choice__body">
                <span class="choice__title">Donation</span>
                <span class="choice__note">Give it away — no points change hands, so no rate applies.</span>
            </span>
        </label>

        <?php if (isset($errors['listing_type'])): ?>
            <span class="field__error"><?= e($errors['listing_type']) ?></span>
        <?php endif; ?>

        <div class="field-row">
            <div class="field">
                <label class="field__label" for="daily-rate">Daily rate (pts)</label>
                <input
                    class="input input--narrow"
                    type="number"
                    id="daily-rate"
                    name="daily_rate"
                    value="<?= e((string) $draft['daily_rate']) ?>"
                    min="1"
                    step="1"
                >
                <?php if (isset($errors['daily_rate'])): ?>
                    <span class="field__error"><?= e($errors['daily_rate']) ?></span>
                <?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="monthly-rate">Monthly rate (pts)</label>
                <input
                    class="input input--narrow"
                    type="number"
                    id="monthly-rate"
                    name="monthly_rate"
                    value="<?= e((string) $draft['monthly_rate']) ?>"
                    min="1"
                    step="1"
                >
                <?php if (isset($errors['monthly_rate'])): ?>
                    <span class="field__error"><?= e($errors['monthly_rate']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <span class="field__hint">Rates are ignored on a donation.</span>

        <?php if (isset($errors['status'])): ?>
            <span class="field__error"><?= e($errors['status']) ?></span>
        <?php endif; ?>

        <div class="actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/items">Cancel</a>
            <button class="btn btn--primary" type="submit">Save changes</button>
        </div>
    </form>

    <?php // Shelf actions post on their own — a form cannot nest inside another. ?>
    <div class="actions">
        <?php if ($item['can_pause']): ?>
            <form method="post" action="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>/pause">
                <?= csrf_field() ?>
                <button class="btn btn--ghost" type="submit">Pause listing</button>
            </form>
        <?php endif; ?>

        <?php if ($item['can_resume']): ?>
            <form method="post" action="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>/resume">
                <?= csrf_field() ?>
                <button class="btn btn--ghost" type="submit">Put back on the shelf</button>
            </form>
        <?php endif; ?>

        <form method="post" action="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>/archive">
            <?= csrf_field() ?>
            <button class="btn btn--ghost" type="submit">Remove listing</button>
        </form>
    </div>

    <p class="field__hint">
        Removing a listing hides it from Browse and from My Items. The record stays, because
        past bookings point at it.
    </p>

<?php endif; ?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
