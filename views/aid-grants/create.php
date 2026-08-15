<?php

declare(strict_types=1);

/**
 * Request an aid grant. Figma: "Aid Grant — Request" (76:106).
 *
 * @var array $purposes select options
 * @var array $draft    values entered so far
 * @var array $errors   per-field messages from the Validator
 */

// Sample view data — replaced by the controller once AidGrantController lands.
$purposes ??= [
    'School supplies',
    'Medical costs',
    'Household essentials',
    'Disaster recovery',
    'Other essential need',
];

$draft ??= [
    'purpose' => 'School supplies',
    'amount'  => '150',
    'details' => '',
];

$errors ??= [];

$pageTitle = 'Request an aid grant';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="detail__title">Request an aid grant</h1>

<p class="record-meta">
    Aid grants come from the community Aid Pool for essential needs. A moderator vouches
    first, then a sponsor liaison approves.
</p>

<form class="panel panel--wide" method="post" action="<?= base_url() ?>/aid-grants" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="field">
        <label class="field__label" for="grant-purpose">Purpose</label>
        <select class="input" id="grant-purpose" name="purpose" required>
            <?php foreach ($purposes as $purpose): ?>
                <option value="<?= e($purpose) ?>"<?= $draft['purpose'] === $purpose ? ' selected' : '' ?>>
                    <?= e($purpose) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['purpose'])): ?>
            <span class="field__error"><?= e($errors['purpose']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="grant-amount">Amount (pts)</label>
        <input
            class="input"
            type="number"
            id="grant-amount"
            name="amount"
            value="<?= e($draft['amount']) ?>"
            min="1"
            step="1"
            required
        >
        <span class="field__hint">
            Grants are sized to need — the liaison may adjust the amount at approval.
        </span>
        <?php if (isset($errors['amount'])): ?>
            <span class="field__error"><?= e($errors['amount']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="grant-details">Tell us more</label>
        <input
            class="input"
            type="text"
            id="grant-details"
            name="details"
            value="<?= e($draft['details']) ?>"
            placeholder="Two children starting the new term, need books and shoes…"
            required
        >
        <?php if (isset($errors['details'])): ?>
            <span class="field__error"><?= e($errors['details']) ?></span>
        <?php endif; ?>
    </div>

    <p class="form-card__legend">Evidence (optional)</p>

    <label class="upload-drop">
        <span class="upload-drop__glyph" aria-hidden="true">＋</span>
        <span>Upload supporting documents — helps vouching go faster</span>
        <input class="visually-hidden" type="file" name="evidence[]" accept="image/*,application/pdf" multiple>
    </label>

    <p class="notice notice--info">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        Your GN division moderator must vouch for this request (with a conflict-of-interest
        declaration) before it goes to the sponsor liaison for approval.
    </p>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/dashboard">Cancel</a>
        <button class="btn btn--primary" type="submit">Submit request</button>
    </div>
</form>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
