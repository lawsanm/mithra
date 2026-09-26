<?php

declare(strict_types=1);

/**
 * Record a sponsor's Disaster Mode contribution (Plan §14.1 step 6, §14.3).
 * The sponsor gave cash or goods to the Moderator off-platform; the Liaison
 * records the sponsor's claim and proof here, and the Moderator is then asked
 * to confirm what they received.
 *
 * @var array $disasters select options: id, title
 * @var array $sponsors  select options: id, name
 * @var array $draft     values entered so far
 * @var array $errors    per-field messages from the Validator
 */

// Sample view data — replaced by the controller once the Liaison's contribution module lands.
$disasters ??= [
    ['id' => 3, 'title' => 'Kollupitiya flooding · active since 15 Jul'],
    ['id' => 2, 'title' => 'Wellawatte landslide · ended 03 Jul'],
];

$sponsors ??= [
    ['id' => 1, 'name' => 'Northwind Co'],
    ['id' => 2, 'name' => 'ACM Corp'],
    ['id' => 3, 'name' => 'Texa'],
    ['id' => 4, 'name' => 'MNM'],
];

$draft ??= [
    'disaster_event_id' => '3',
    'sponsor_id'        => '',
    'contribution_kind' => 'goods',
    'description'       => '',
    'estimated_value'   => '',
    'handed_over_on'    => '',
    'receipt_reference' => '',
    'notes'             => '',
];

$errors ??= [];

$pageTitle = 'Record a disaster contribution';
$navActive = 'disasters';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/disasters">Disasters</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/disasters/contributions">Contributions</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Record</span>
</nav>

<h1 class="detail__title">Record a disaster contribution</h1>

<div class="form-card" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>

    <?php include __DIR__ . '/../../../../partials/disaster-contribution-fields.php'; ?>

    <p class="notice notice--info notice--full">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        Saving sends this to the division's Moderator, who confirms what they actually
        received and attaches their acknowledgement. You verify once both accounts are in.
        No points move — Disaster Mode contributions are record-keeping for CSR reports only.
    </p>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/disasters/contributions">Cancel</a>
        <button class="btn btn--primary" type="submit" disabled>Record and ask Moderator to confirm</button>
    </div>
</div>

<?php include __DIR__ . '/../../../../partials/footer.php'; ?>
