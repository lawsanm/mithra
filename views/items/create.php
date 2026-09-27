<?php

declare(strict_types=1);

/**
 * Create listing wizard. Figma: "Create Listing — Step 1…4"
 * (70:139, 70:208, 70:271, 70:334). One view per controller action; $step
 * selects which panel of the same action renders.
 *
 * Every step posts to /items. The controller keeps the half-finished listing in
 * the session, so a refresh or a Back link never loses what was typed.
 *
 * @var int    $step       1–4
 * @var array  $categories rows from item_categories: id, name
 * @var array  $draft      values entered so far
 * @var array  $photos     proxy URLs of the photos already uploaded
 * @var array  $errors     per-field messages from the Validator
 * @var string $summary    one-line recap shown on the last step
 * @var array  $proofTypes value => label for the proof-of-value kinds (Plan §9.1)
 */

$step       = $step ?? 1;
$categories = $categories ?? [];
$proofTypes = $proofTypes ?? [];
$draft      = $draft ?? [];
$photos     = $photos ?? [];
$errors     = $errors ?? [];
$summary    = $summary ?? '';

$isDonation = ($draft['listing_type'] ?? 'rental') === 'donation';

$steps = [
    1 => 'Item & photos',
    2 => 'Declared value',
    3 => 'Listing type',
    4 => $isDonation ? 'Confirm' : 'Set rate',
];

$pageTitle = 'List an item';
$navActive = 'items';

include __DIR__ . '/../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/items">My Items</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">New listing</span>
</nav>

<h1 class="detail__title">List an item</h1>

<?php $wizardSteps = $steps; $wizardStep = $step; include __DIR__ . '/../../partials/wizard-steps.php'; ?>

<form class="form-card" method="post" action="<?= base_url() ?>/items" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="step" value="<?= e((string) $step) ?>">

    <?php if ($step === 1): ?>

        <?php include __DIR__ . '/../../partials/item-details.php'; ?>

        <p class="form-card__legend">Photos</p>

        <div class="field-row">
            <?php foreach ($photos as $photo): ?>
                <img class="thumb thumb--sm thumb__img" src="<?= e($photo) ?>" alt="">
            <?php endforeach; ?>
            <label class="upload-tile">
                <span aria-hidden="true">＋</span>
                <span class="visually-hidden">Add a photo</span>
                <input class="visually-hidden" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
            </label>
        </div>

        <p class="field__hint">
            Add up to 5 photos, 5 MB each. Clear, well-lit photos build borrower trust.
            Photos upload when you press Continue.
        </p>
        <?= field_error($errors, 'photos') ?>

        <div class="actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/items">Cancel</a>
            <button class="btn btn--primary" type="submit">Continue</button>
        </div>

    <?php elseif ($step === 2): ?>

        <?php include __DIR__ . '/../../partials/item-value.php'; ?>

        <label class="upload-drop">
            <span class="upload-drop__glyph" aria-hidden="true">＋</span>
            <span data-upload-name>
                <?= $draft['value_proof_path'] !== null ? 'Proof uploaded — choose another to replace it' : 'Upload a photo of the receipt, warranty card or price reference' ?>
            </span>
            <input class="visually-hidden" type="file" name="value_proof" accept="image/jpeg,image/png,image/webp">
        </label>
        <?= field_error($errors, 'value_proof') ?>

        <p class="notice notice--info">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            Your GN division moderator reviews the declared value before the listing goes
            live. Inflated values are adjusted or rejected.
        </p>

        <div class="actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/items/create?step=1">Back</a>
            <button class="btn btn--primary" type="submit">Continue</button>
        </div>

    <?php elseif ($step === 3): ?>

        <p class="form-card__legend">How do you want to share this item?</p>

        <label class="choice">
            <input
                class="choice__input"
                type="radio"
                name="listing_type"
                value="rental"
                <?= !$isDonation ? 'checked' : '' ?>
            >
            <span class="choice__body">
                <span class="choice__title">Rental</span>
                <span class="choice__note">
                    Lend for points per day or month. Points are held in escrow while the item is out.
                </span>
            </span>
        </label>

        <label class="choice">
            <input
                class="choice__input"
                type="radio"
                name="listing_type"
                value="donation"
                <?= $isDonation ? 'checked' : '' ?>
            >
            <span class="choice__body">
                <span class="choice__title">Donation</span>
                <span class="choice__note">
                    Give it away to a member who requests it. Earns you a Donor badge on your profile.
                </span>
            </span>
        </label>

        <?= field_error($errors, 'listing_type') ?>

        <div class="actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/items/create?step=2">Back</a>
            <button class="btn btn--primary" type="submit">Continue</button>
        </div>

    <?php else: ?>

        <?php if ($isDonation): ?>

            <p class="notice notice--info">
                <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
                A donation moves no points, so it carries no rate. Members in your division
                request it and you choose who receives it.
            </p>

        <?php else: ?>

            <?php include __DIR__ . '/../../partials/item-rates.php'; ?>

            <p class="notice notice--amber">
                Set a daily rate, a monthly rate, or both — a rental needs at least one.
                Monthly rates usually run about 10× the daily rate, and the system shows
                borrowers whichever works out cheaper.
            </p>

        <?php endif; ?>

        <p class="summary">
            <span class="summary__label">Summary</span>
            <span class="summary__value"><?= e($summary) ?></span>
        </p>

        <div class="actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/items/create?step=3">Back</a>
            <button class="btn btn--primary" type="submit">Submit for approval</button>
        </div>

    <?php endif; ?>
</form>

<?php $pageScripts = ['upload-name.js']; ?>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
