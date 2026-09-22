<?php

declare(strict_types=1);

/**
 * Onboard a sponsor. Figma "Sponsor — Onboarding" (377:155).
 *
 * @var array $draft  values entered so far
 * @var array $errors per-field messages from the Validator
 */

// Sample view data — replaced by the controller once SponsorLiaisonController lands.
$draft ??= [
    'company_name'       => '',
    'contact_person'     => '',
    'contact_email'      => '',
    'agreement_status'   => '',
    'agreement_details'  => '',
    'internal_notes'     => '',
];

$errors ??= [];

$agreementStatuses = [
    'signed'  => 'Signed agreement on file',
    'pending' => 'Pending signature',
    'verbal'  => 'Verbal agreement only',
];

$pageTitle = 'Onboard a sponsor';
$navActive = 'sponsors';

include __DIR__ . '/../../../partials/header-sponsor-liaison.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/sponsors">Sponsors</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">New sponsor</span>
</nav>

<h1 class="detail__title">Onboard a sponsor</h1>

<div class="form-card" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
    

    <div class="field">
        <label class="field__label" for="company-name">Company name</label>
        <input
            class="input"
            type="text"
            id="company-name"
            name="company_name"
            value="<?= e($draft['company_name']) ?>"
            placeholder="Northwind Co"
            required
         disabled>
        <?php if (isset($errors['company_name'])): ?>
            <span class="field__error"><?= e($errors['company_name']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="contact-person">Contact person</label>
        <input
            class="input"
            type="text"
            id="contact-person"
            name="contact_person"
            value="<?= e($draft['contact_person']) ?>"
            placeholder="T.H.K. Madushan"
            required
         disabled>
        <?php if (isset($errors['contact_person'])): ?>
            <span class="field__error"><?= e($errors['contact_person']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="contact-email">Contact email</label>
        <input
            class="input"
            type="email"
            id="contact-email"
            name="contact_email"
            value="<?= e($draft['contact_email']) ?>"
            placeholder="contact@northwind.lk"
            required
         disabled>
        <?php if (isset($errors['contact_email'])): ?>
            <span class="field__error"><?= e($errors['contact_email']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="agreement-status">Agreement status</label>
        <select class="input" id="agreement-status" name="agreement_status" required disabled>
            <option value="">Select status</option>
            <?php foreach ($agreementStatuses as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $draft['agreement_status'] === $value ? ' selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['agreement_status'])): ?>
            <span class="field__error"><?= e($errors['agreement_status']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="agreement-details">Agreement details</label>
        <input
            class="input"
            type="text"
            id="agreement-details"
            name="agreement_details"
            value="<?= e($draft['agreement_details']) ?>"
            placeholder="CSR agreement ref, contribution schedule"
         disabled>
        <?php if (isset($errors['agreement_details'])): ?>
            <span class="field__error"><?= e($errors['agreement_details']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="internal-notes">Internal notes</label>
        <input
            class="input"
            type="text"
            id="internal-notes"
            name="internal_notes"
            value="<?= e($draft['internal_notes']) ?>"
            placeholder="Notes about this sponsor (visible to liaisons only)"
         disabled>
        <?php if (isset($errors['internal_notes'])): ?>
            <span class="field__error"><?= e($errors['internal_notes']) ?></span>
        <?php endif; ?>
    </div>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/sponsors">Cancel</a>
        <button class="btn btn--primary" type="submit" disabled>Connect sponsor</button>
    </div>
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
