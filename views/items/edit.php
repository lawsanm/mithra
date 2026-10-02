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
 * @var array $proofTypes value => label for the proof-of-value kinds (Plan §9.1)
 * @var bool  $proofOnFile whether a proof document is already stored
 * @var array|null $flash
 */

$item       = $item ?? [];
$categories = $categories ?? [];
$photos     = $photos ?? [];
$draft      = $draft ?? [];
$errors     = $errors ?? [];
$proofTypes = $proofTypes ?? [];
$proofOnFile = $proofOnFile ?? false;

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

    <form class="form-card" method="post" action="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <?php include __DIR__ . '/../../partials/item-details.php'; ?>

        <?php include __DIR__ . '/../../partials/item-value.php'; ?>

        <div class="field">
            <label class="field__label" for="value-proof">
                <?= $proofOnFile ? 'Replace the proof on file — optional' : 'Proof document' ?>
            </label>
            <input class="input" type="file" id="value-proof" name="value_proof" accept="image/jpeg,image/png,image/webp">
            <?= field_error($errors, 'value_proof') ?>
        </div>

        <p class="form-card__legend">Photos</p>

        <div class="field-row">
            <?php foreach ($photos as $photo): ?>
                <div class="field">
                    <img class="thumb thumb--sm thumb__img thumb--item" src="<?= e($photo['url']) ?>" alt="Item photo" loading="lazy" decoding="async">
                    <label class="field__hint">
                        <input type="checkbox" name="keep_photos[]" value="<?= e($photo['path']) ?>" checked>
                        Keep
                    </label>
                </div>
            <?php endforeach; ?>
            <label class="upload-tile">
                <span aria-hidden="true">＋</span>
                <span class="visually-hidden">Add a photo</span>
                <input class="visually-hidden" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple
                    data-max-files="<?= e((string) ItemService::MAX_PHOTOS) ?>" data-kept-by="keep_photos[]">
            </label>
        </div>

        <p class="field__hint">
            Clear a "Keep" box to drop that photo when you save. A listing needs at least one.
        </p>
        <?= field_error($errors, 'photos') ?>

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

        <?= field_error($errors, 'listing_type') ?>

        <?php include __DIR__ . '/../../partials/item-rates.php'; ?>

        <span class="field__hint">A rental needs a daily rate, a monthly rate, or both. Rates are ignored on a donation.</span>

        <?= field_error($errors, 'status') ?>

        <div class="actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/items">Cancel</a>
            <button class="btn btn--primary" type="submit">Save changes</button>
        </div>
    </form>

    <?php // Shelf actions post on their own — a form cannot nest inside another. ?>
    <div class="actions">
        <?php if ($item['can_pause']): ?>
            <form method="post" action="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>/pause" novalidate>
                <?= csrf_field() ?>
                <button class="btn btn--ghost" type="submit">Pause listing</button>
            </form>
        <?php endif; ?>

        <?php if ($item['can_resume']): ?>
            <form method="post" action="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>/resume" novalidate>
                <?= csrf_field() ?>
                <button class="btn btn--ghost" type="submit">Put back on the shelf</button>
            </form>
        <?php endif; ?>

        <form method="post" action="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>/archive"
            data-confirm="Remove this listing? It will disappear from Browse and My Items, and this cannot be undone." novalidate>
            <?= csrf_field() ?>
            <button class="btn btn--ghost" type="submit">Remove listing</button>
        </form>
    </div>

    <p class="field__hint">
        Removing a listing hides it from Browse and from My Items. The record stays, because
        past bookings point at it.
    </p>

<?php endif; ?>

<?php if (($blocks ?? null) !== null): ?>
    <section class="panel panel--wide" id="availability">
        <h2 class="panel__title">Availability calendar</h2>
        <p class="record-meta">Block dates you need the item yourself. Borrowers cannot request them.</p>

        <?php if ($blocks === []): ?>
            <p class="record-meta">No dates blocked.</p>
        <?php else: ?>
            <ul class="row-list">
                <?php foreach ($blocks as $block): ?>
                    <li class="list-row">
                        <form class="field-row" method="post" action="<?= base_url() ?>/availability-blocks/<?= e((string) $block['id']) ?>" novalidate>
                            <?= csrf_field() ?>
                            <div class="field">
                                <label class="visually-hidden" for="block-start-<?= e((string) $block['id']) ?>">From</label>
                                <input class="input input--date" type="date" id="block-start-<?= e((string) $block['id']) ?>" name="start_date" value="<?= e($block['start']) ?>">
                            </div>
                            <div class="field">
                                <label class="visually-hidden" for="block-end-<?= e((string) $block['id']) ?>">To</label>
                                <input class="input input--date" type="date" id="block-end-<?= e((string) $block['id']) ?>" name="end_date" value="<?= e($block['end']) ?>">
                            </div>
                            <div class="field">
                                <label class="visually-hidden" for="block-note-<?= e((string) $block['id']) ?>">Note</label>
                                <input class="input" type="text" id="block-note-<?= e((string) $block['id']) ?>" name="note" value="<?= e($block['note']) ?>" placeholder="Note (optional)">
                            </div>
                            <button class="btn btn--ghost" type="submit">Save</button>
                        </form>
                        <form method="post" action="<?= base_url() ?>/availability-blocks/<?= e((string) $block['id']) ?>/delete"
                            data-confirm="Open <?= e($block['label']) ?> again? Borrowers will be able to request these dates." novalidate>
                            <?= csrf_field() ?>
                            <button class="btn btn--ghost" type="submit">Unblock</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form class="field-row" method="post" action="<?= base_url() ?>/items/<?= e((string) $item['id']) ?>/availability" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label class="field__label" for="new-block-start">From</label>
                <input class="input input--date" type="date" id="new-block-start" name="start_date">
            </div>
            <div class="field">
                <label class="field__label" for="new-block-end">To</label>
                <input class="input input--date" type="date" id="new-block-end" name="end_date">
            </div>
            <div class="field">
                <label class="field__label" for="new-block-note">Note (optional)</label>
                <input class="input" type="text" id="new-block-note" name="note">
            </div>
            <button class="btn btn--primary" type="submit">Block dates</button>
        </form>
    </section>
<?php endif; ?>

<?php $pageScripts = ['upload-name.js', 'confirm.js']; ?>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
