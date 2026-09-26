<?php

declare(strict_types=1);

/**
 * Edit a disaster contribution before it is verified (Plan §14.3). Once the
 * Liaison verifies it the record is locked so the CSR figures stay put.
 * Changing the value, kind or date sends it back to the Moderator to confirm.
 *
 * @var int   $id        contribution id from the URL
 * @var array $disasters select options: id, title
 * @var array $sponsors  select options: id, name
 * @var array $draft     current field values, plus reference
 * @var array $errors    per-field messages from the Validator
 */

$showUrl = base_url() . '/sponsor-liaison/disasters/contributions/' . rawurlencode((string) $id);

$pageTitle = 'Edit ' . $draft['reference'];
$navActive = 'disasters';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/disasters">Disasters</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/disasters/contributions">Contributions</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <a class="breadcrumb__link" href="<?= e($showUrl) ?>"><?= e($draft['reference']) ?></a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Edit</span>
</nav>

<h1 class="detail__title">Edit contribution <?= e($draft['reference']) ?></h1>

<div class="form-card" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>

    <?php include __DIR__ . '/../../../../partials/disaster-contribution-fields.php'; ?>

    <p class="notice notice--warning notice--full">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        Changing the kind, value or date clears the Moderator's confirmation and asks
        them to confirm again. Verified contributions cannot be edited.
    </p>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= e($showUrl) ?>">Cancel</a>
        <button class="btn btn--primary" type="submit" disabled>Save changes</button>
    </div>
</div>

<?php include __DIR__ . '/../../../../partials/footer.php'; ?>
