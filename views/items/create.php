<?php

declare(strict_types=1);

/**
 * Create listing wizard. Figma: "Create Listing — Step 1…4"
 * (70:139, 70:208, 70:271, 70:334). One view per controller action; $step
 * selects which panel of the same action renders.
 *
 * @var int    $step       1–4
 * @var array  $categories select options for step 1
 * @var array  $draft      values entered so far
 * @var array  $errors     per-field messages from the Validator
 */

// Sample view data — replaced by the controller once ItemController lands.
$step ??= (int) ($_GET['step'] ?? 1);
$step = max(1, min(4, $step));

$categories ??= ['Tools', 'Electronics', 'Kitchen', 'Outdoor', 'Books', 'Baby & Kids', 'Events'];

$draft ??= [
    'name'           => '',
    'category'       => '',
    'photo_count'    => 2,
    'declared_value' => '300',
    'listing_type'   => 'rental',
    'daily_rate'     => '15',
    'monthly_rate'   => '150',
    'summary'        => 'Tools  ·  Rental  ·  declared 300 pts  ·  15 pts/day or 150 pts/month',
];

$errors ??= [];

$steps = [
    1 => 'Category & photos',
    2 => 'Declared value',
    3 => 'Listing type',
    4 => 'Set rate',
];

$pageTitle = 'List an item';
$navActive = 'items';

include __DIR__ . '/../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="/items">My Items</a>
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

<form class="form-card" method="post" action="/items" enctype="multipart/form-data">
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
                value="<?= e($draft['name']) ?>"
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
                    <option value="<?= e($category) ?>"<?= $draft['category'] === $category ? ' selected' : '' ?>>
                        <?= e($category) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['category'])): ?>
                <span class="field__error"><?= e($errors['category']) ?></span>
            <?php endif; ?>
        </div>

        <p class="form-card__legend">Photos</p>

        <div class="field-row">
            <?php for ($photo = 1; $photo <= $draft['photo_count']; $photo++): ?>
                <span class="thumb thumb--upload"></span>
            <?php endfor; ?>
            <label class="upload-tile">
                <span aria-hidden="true">＋</span>
                <span class="visually-hidden">Add a photo</span>
                <input class="visually-hidden" type="file" name="photos[]" accept="image/*" multiple>
            </label>
        </div>

        <p class="field__hint">Add up to 5 photos. Clear, well-lit photos build borrower trust.</p>

        <div class="actions">
            <a class="btn btn--ghost" href="/items">Cancel</a>
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
                value="<?= e($draft['declared_value']) ?>"
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
            <span>Upload a receipt, invoice or comparable ad</span>
            <input class="visually-hidden" type="file" name="value_proof" accept="image/*,application/pdf">
        </label>

        <p class="notice notice--info">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            Your GN division moderator reviews the declared value before the listing goes
            live. Inflated values are adjusted or rejected.
        </p>

        <div class="actions">
            <a class="btn btn--ghost" href="/items/create?step=1">Back</a>
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
                <?= $draft['listing_type'] === 'rental' ? 'checked' : '' ?>
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
                <?= $draft['listing_type'] === 'donation' ? 'checked' : '' ?>
            >
            <span class="choice__body">
                <span class="choice__title">Donation</span>
                <span class="choice__note">
                    Give it away to a member who requests it. Earns you a Donor badge on your profile.
                </span>
            </span>
        </label>

        <div class="actions">
            <a class="btn btn--ghost" href="/items/create?step=2">Back</a>
            <button class="btn btn--primary" type="submit">Continue</button>
        </div>

    <?php else: ?>

        <div class="field-row">
            <div class="field">
                <label class="field__label" for="daily-rate">Daily rate (pts)</label>
                <input
                    class="input input--narrow"
                    type="number"
                    id="daily-rate"
                    name="daily_rate"
                    value="<?= e($draft['daily_rate']) ?>"
                    min="1"
                    step="1"
                    required
                >
            </div>
            <div class="field">
                <label class="field__label" for="monthly-rate">Monthly rate (pts) — optional</label>
                <input
                    class="input input--narrow"
                    type="number"
                    id="monthly-rate"
                    name="monthly_rate"
                    value="<?= e($draft['monthly_rate']) ?>"
                    min="1"
                    step="1"
                >
            </div>
        </div>

        <p class="notice notice--amber">
            Pricing tip: similar drills in Kollupitiya rent for 12–18 pts/day. Monthly rates
            usually run about 10× the daily rate — the system will highlight the cheaper
            option to borrowers.
        </p>

        <p class="summary">
            <span class="summary__label">Summary</span>
            <span class="summary__value"><?= e($draft['summary']) ?></span>
        </p>

        <div class="actions">
            <a class="btn btn--ghost" href="/items/create?step=3">Back</a>
            <button class="btn btn--primary" type="submit">Submit for approval</button>
        </div>

    <?php endif; ?>
</form>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
