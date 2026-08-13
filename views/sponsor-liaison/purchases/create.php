<?php

declare(strict_types=1);

/**
 * Record a sponsor contribution. Figma "Record Contribution" (382:103).
 *
 * @var array $sponsors select options: id, name
 * @var array $draft    values entered so far, plus sponsor_pct/aid_pct for the split bar
 * @var array $errors   per-field messages from the Validator
 */

// Sample view data — replaced by the controller once SponsorLiaisonController lands.
$sponsors ??= [
    ['id' => 1, 'name' => 'Northwind Co'],
    ['id' => 2, 'name' => 'ACM Corp'],
    ['id' => 3, 'name' => 'Texa'],
    ['id' => 4, 'name' => 'MNM'],
];

$draft ??= [
    'sponsor_id'      => '',
    'amount'          => '',
    'receipt_number'  => '',
    'sponsor_pct'     => 70,
    'aid_pct'         => 30,
];

$errors ??= [];

$splitPresets = ['100/0', '70/30', '50/50', '0/100'];
$currentSplit = $draft['sponsor_pct'] . '/' . $draft['aid_pct'];

$amountValue    = (int) ($draft['amount'] ?: 100000);
$sponsorPoints  = number_format((int) round($amountValue * $draft['sponsor_pct'] / 100));
$aidPoints      = number_format((int) round($amountValue * $draft['aid_pct'] / 100));

$pageTitle = 'Record a contribution';
$navActive = 'purchases';

include __DIR__ . '/../../../partials/header-sponsor-liaison.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="/sponsor-liaison/purchases">Purchases</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Record contribution</span>
</nav>

<h1 class="detail__title">Record a sponsor contribution</h1>

<form class="form-card" method="post" action="/sponsor-liaison/purchases">
    <?= csrf_field() ?>

    <div class="field">
        <label class="field__label" for="sponsor-id">Sponsor</label>
        <select class="input" id="sponsor-id" name="sponsor_id" required>
            <option value="">Select sponsor</option>
            <?php foreach ($sponsors as $sponsor): ?>
                <option value="<?= e((string) $sponsor['id']) ?>"<?= (string) $draft['sponsor_id'] === (string) $sponsor['id'] ? ' selected' : '' ?>>
                    <?= e($sponsor['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['sponsor_id'])): ?>
            <span class="field__error"><?= e($errors['sponsor_id']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field-row">
        <div class="field">
            <label class="field__label" for="amount">Amount (LKR)</label>
            <input
                class="input input--half"
                type="number"
                id="amount"
                name="amount"
                value="<?= e((string) $draft['amount']) ?>"
                placeholder="100,000"
                min="1"
                step="1"
                required
            >
            <?php if (isset($errors['amount'])): ?>
                <span class="field__error"><?= e($errors['amount']) ?></span>
            <?php endif; ?>
        </div>
        <div class="field">
            <label class="field__label" for="receipt-number">Receipt number</label>
            <input
                class="input input--half"
                type="text"
                id="receipt-number"
                name="receipt_number"
                value="<?= e($draft['receipt_number']) ?>"
                placeholder="INV-0318"
                required
            >
            <?php if (isset($errors['receipt_number'])): ?>
                <span class="field__error"><?= e($errors['receipt_number']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <p class="form-card__legend">Allocation split — the sponsor's choice</p>

    <div class="split-bar">
        <span class="split-bar__segment split-bar__segment--sponsor" style="width: <?= (int) $draft['sponsor_pct'] ?>%;"></span>
        <span class="split-bar__segment split-bar__segment--aid" style="width: <?= (int) $draft['aid_pct'] ?>%;"></span>
    </div>

    <div class="split-legend">
        <span class="split-legend__item">
            <span class="split-legend__dot split-legend__dot--sponsor"></span>
            Sponsor Pool  ·  <?= (int) $draft['sponsor_pct'] ?>%  =  <?= e($sponsorPoints) ?> pts
        </span>
        <span class="split-legend__item">
            <span class="split-legend__dot split-legend__dot--aid"></span>
            Aid Pool  ·  <?= (int) $draft['aid_pct'] ?>%  =  <?= e($aidPoints) ?> pts
        </span>
    </div>

    <div class="filter-pills">
        <?php foreach ($splitPresets as $preset): ?>
            <label class="pill">
                <input class="visually-hidden" type="radio" name="split" value="<?= e($preset) ?>"<?= $preset === $currentSplit ? ' checked' : '' ?>>
                <?= e(str_replace('/', ' / ', $preset)) ?>
            </label>
        <?php endforeach; ?>
    </div>

    <p class="notice notice--info notice--full">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        1 rupee = 1 point, no deductions. Cash is collected offline under the written
        agreement — the platform records only the resulting points. The split is
        permanently logged against this contribution and shown on the Transparency
        Dashboard.
    </p>

    <div class="actions">
        <a class="btn btn--ghost" href="/sponsor-liaison/purchases">Cancel</a>
        <button class="btn btn--primary" type="submit">Record contribution</button>
    </div>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
