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
    'purpose' => '',
    'amount'  => '',
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

<div class="panel panel--wide" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
    <div class="field">
        <label class="field__label" for="grant-purpose">Purpose</label>
        <select class="input" id="grant-purpose" name="purpose" disabled>
            <?php foreach ($purposes as $purpose): ?>
                <option value="<?= e($purpose) ?>"<?= $draft['purpose'] === $purpose ? ' selected' : '' ?>>
                    <?= e($purpose) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'purpose') ?>
    </div>

    <div class="field">
        <label class="field__label" for="grant-amount">Amount (pts)</label>
        <input
            class="input"
            type="number"
            id="grant-amount"
            name="amount"
            value="<?= e($draft['amount']) ?>"
         disabled>
        <span class="field__hint">
            Grants are sized to need — the liaison may adjust the amount at approval.
        </span>
        <?= field_error($errors, 'amount') ?>
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
         disabled>
        <?= field_error($errors, 'details') ?>
    </div>

    <p class="form-card__legend">Evidence (optional)</p>

    <label class="upload-drop">
        <span class="upload-drop__glyph" aria-hidden="true">＋</span>
        <span>Upload supporting documents — helps vouching go faster</span>
        <input class="visually-hidden" type="file" name="evidence[]" accept="image/jpeg,image/png,image/webp" multiple disabled>
    </label>

    <p class="notice notice--info">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        Your GN division moderator must vouch for this request (with a conflict-of-interest
        declaration) before it goes to the sponsor liaison for approval.
    </p>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/dashboard">Cancel</a>
        <button class="btn btn--primary" type="submit" disabled>Submit request</button>
    </div>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
