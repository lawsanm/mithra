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
 */

$step       = $step ?? 1;
$categories = $categories ?? [];
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

<ol class="wizard">
    <?php foreach ($steps as $number => $label): ?>
        <?php if ($number > 1): ?>
            <li aria-hidden="true"><hr class="wizard__connector"></li>
        <?php endif; ?>
        <?php
        $isDone    = $number < $step;
        $isCurrent = $number === $step;
        ?>
        <li class="wizard__step">
            <span class="wizard__marker<?= $isCurrent ? ' wizard__marker--current' : ($isDone ? ' wizard__marker--done' : '') ?>">
                <?= $isDone ? '✓' : $number ?>
            </span>
            <span class="wizard__label<?= $isCurrent ? ' wizard__label--current' : '' ?>"
                <?= $isCurrent ? 'aria-current="step"' : '' ?>><?= e($label) ?></span>
        </li>
    <?php endforeach; ?>
</ol>

<form class="form-card" method="post" action="<?= base_url() ?>/items" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="step" value="<?= e((string) $step) ?>">

    <?php if ($step === 1): ?>

        <div class="field">
            <label class="field__label" for="item-name">Item name</label>
            <input
                class="input"
                type="text"
                id="item-name"
                name="name"
                value="<?= e((string) $draft['name']) ?>"
                maxlength="150"
                placeholder="e.g. Bosch Cordless Drill GSB 120"
                required
            >
            <?php if (isset($errors['name'])): ?>
                <span class="field__error"><?= e($errors['name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="item-category">Category</label>
            <select class="input" id="item-category" name="category" required>
                <option value="">Select category</option>
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
                placeholder="Condition, what is included, anything a borrower should know."
            ><?= e((string) $draft['description']) ?></textarea>
            <span class="field__hint">Borrowers search this text, so name the brand and the accessories.</span>
            <?php if (isset($errors['description'])): ?>
                <span class="field__error"><?= e($errors['description']) ?></span>
            <?php endif; ?>
        </div>

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
        <?php if (isset($errors['photos'])): ?>
            <span class="field__error"><?= e($errors['photos']) ?></span>
        <?php endif; ?>

        <div class="actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/items">Cancel</a>
            <button class="btn btn--primary" type="submit">Continue</button>
        </div>

    <?php elseif ($step === 2): ?>

        <div class="field">
            <label class="field__label" for="declared-value">Declared value (pts)</label>
            <input
                class="input"
                type="number"
                id="declared-value"
                name="declared_value"
                value="<?= e((string) $draft['declared_value']) ?>"
                min="1"
                step="1"
                required
            >
            <span class="field__hint">Used to size the security hold and cap any damage claim.</span>
            <?php if (isset($errors['declared_value'])): ?>
                <span class="field__error"><?= e($errors['declared_value']) ?></span>
            <?php endif; ?>
        </div>

        <p class="form-card__legend">Proof of value</p>

        <label class="upload-drop">
            <span class="upload-drop__glyph" aria-hidden="true">＋</span>
            <span>
                <?= $draft['value_proof_path'] !== null ? 'Proof uploaded — choose another to replace it' : 'Upload a photo of a receipt, invoice or comparable ad' ?>
            </span>
            <input class="visually-hidden" type="file" name="value_proof" accept="image/jpeg,image/png,image/webp">
        </label>
        <?php if (isset($errors['value_proof'])): ?>
            <span class="field__error"><?= e($errors['value_proof']) ?></span>
        <?php endif; ?>

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

        <?php if (isset($errors['listing_type'])): ?>
            <span class="field__error"><?= e($errors['listing_type']) ?></span>
        <?php endif; ?>

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
                    <label class="field__label" for="monthly-rate">Monthly rate (pts) — optional</label>
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

<?php include __DIR__ . '/../../partials/footer.php'; ?>
