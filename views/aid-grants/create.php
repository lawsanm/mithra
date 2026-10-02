<?php

declare(strict_types=1);

/**
 * Request an aid grant, or change one before the vouch. Figma: "Aid Grant —
 * Request" (76:106).
 *
 * @var list<string>    $purposes  the allowed purposes
 * @var array           $draft     purpose, amount, details
 * @var array           $errors    per-field messages
 * @var array|null      $editing   the request being changed, or null for a new one
 * @var string          $blocked   why the member cannot ask now, '' when they can
 * @var int             $remaining points still available this year
 */

$isEdit    = $editing !== null;
$pageTitle = $isEdit ? 'Change aid request' : 'Request an aid grant';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="detail__title"><?= e($pageTitle) ?></h1>

<p class="record-meta">
    Aid grants come from the community Aid Pool for essential needs. Your GN division
    moderator vouches first, then the Sponsor Liaison approves.
</p>

<?php if ($blocked !== ''): ?>
    <p class="notice notice--warning">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-alert-triangle"></use></svg>
        <?= e($blocked) ?>
    </p>
    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/aid-grants">Back to aid grants</a>
    </div>
<?php else: ?>
    <?php if (isset($errors['form'])): ?>
        <p class="notice notice--error"><?= e($errors['form']) ?></p>
    <?php endif; ?>

    <form class="panel panel--wide" method="post" action="<?= base_url() ?>/aid-grants<?= $isEdit ? '/' . e((string) $editing['id']) : '' ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label class="field__label" for="grant-purpose">Purpose</label>
            <select class="input" id="grant-purpose" name="purpose">
                <option value="">Choose…</option>
                <?php foreach ($purposes as $purpose): ?>
                    <option value="<?= e($purpose) ?>"<?= $draft['purpose'] === $purpose ? ' selected' : '' ?>><?= e($purpose) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'purpose') ?>
        </div>

        <div class="field">
            <label class="field__label" for="grant-amount">Amount (pts)</label>
            <input class="input" type="number" id="grant-amount" name="amount" value="<?= e((string) $draft['amount']) ?>">
            <span class="field__hint">
                Up to <?= e((string) $remaining) ?> pts this year. The liaison may adjust the amount at approval.
            </span>
            <?= field_error($errors, 'amount') ?>
        </div>

        <div class="field">
            <label class="field__label" for="grant-details">Tell us more</label>
            <input class="input" type="text" id="grant-details" name="details" value="<?= e((string) $draft['details']) ?>"
                placeholder="Two children starting the new term, need books and shoes…">
            <?= field_error($errors, 'details') ?>
        </div>

        <?php if (!$isEdit): ?>
            <p class="form-card__legend">Evidence (optional)</p>
            <label class="upload-drop">
                <span class="upload-drop__glyph" aria-hidden="true">＋</span>
                <span data-upload-name>Upload up to <?= e((string) AidGrantService::MAX_PHOTOS) ?> photos — helps vouching go faster</span>
                <input class="visually-hidden" type="file" name="evidence[]" accept="image/jpeg,image/png,image/webp" multiple>
            </label>
            <?= field_error($errors, 'evidence') ?>
        <?php endif; ?>

        <p class="notice notice--info">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            Your GN division moderator must vouch for this request (with a conflict-of-interest
            declaration) before it goes to the sponsor liaison for approval.
        </p>

        <div class="actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/<?= $isEdit ? 'aid-grants/' . e((string) $editing['id']) : 'aid-grants' ?>">Cancel</a>
            <button class="btn btn--primary" type="submit"><?= $isEdit ? 'Save changes' : 'Submit request' ?></button>
        </div>
    </form>
<?php endif; ?>

<?php
$pageScripts = ['upload-name.js'];
include __DIR__ . '/../../partials/footer.php';
?>
