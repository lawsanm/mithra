<?php

declare(strict_types=1);

/**
 * A sponsor's company, contact and agreement fields, shared by onboarding and
 * the edit form so the wording never drifts between the two.
 *
 * @var array $draft             current field values
 * @var array $errors            per-field messages
 * @var array $agreementStatuses value => label
 * @var bool  $contactRequired   true at onboarding, where the sponsor login is
 *                               in the contact person's name and uses the contact email
 */

$contactRequired = $contactRequired ?? false;
$contactSuffix   = $contactRequired ? '' : ' — optional';

?>
<div class="field">
    <label class="field__label" for="company-name">Company name</label>
    <input
        class="input"
        type="text"
        id="company-name"
        name="company_name"
        value="<?= e($draft['company_name']) ?>"
        placeholder="Northwind Co"
        <?= isset($errors['company_name']) ? 'aria-invalid="true"' : '' ?>
    >
    <?= field_error($errors, 'company_name') ?>
</div>

<div class="field">
    <label class="field__label" for="contact-person">Contact person<?= e($contactSuffix) ?></label>
    <input
        class="input"
        type="text"
        id="contact-person"
        name="contact_person"
        value="<?= e($draft['contact_person']) ?>"
        placeholder="R. Fernando"
        <?= isset($errors['contact_person']) ? 'aria-invalid="true"' : '' ?>
    >
    <?= field_error($errors, 'contact_person') ?>
</div>

<div class="field">
    <label class="field__label" for="contact-email">Contact email<?= e($contactSuffix) ?></label>
    <input
        class="input"
        type="email"
        id="contact-email"
        name="contact_email"
        value="<?= e($draft['contact_email']) ?>"
        placeholder="contact@northwind.lk"
        <?= isset($errors['contact_email']) ? 'aria-invalid="true"' : '' ?>
    >
    <?= field_error($errors, 'contact_email') ?>
</div>

<div class="field">
    <label class="field__label" for="contact-phone">Contact phone — optional</label>
    <input
        class="input"
        type="tel"
        id="contact-phone"
        name="contact_phone"
        value="<?= e($draft['contact_phone']) ?>"
        placeholder="+94 11 234 5678"
        <?= isset($errors['contact_phone']) ? 'aria-invalid="true"' : '' ?>
    >
    <?= field_error($errors, 'contact_phone') ?>
</div>

<div class="field">
    <label class="field__label" for="agreement-status">Agreement status</label>
    <select
        class="input"
        id="agreement-status"
        name="agreement_status"
        <?= isset($errors['agreement_status']) ? 'aria-invalid="true"' : '' ?>
    >
        <option value="">Select status</option>
        <?php foreach ($agreementStatuses as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $draft['agreement_status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <span class="field__hint">Contributions are only taken once a written agreement is signed.</span>
    <?= field_error($errors, 'agreement_status') ?>
</div>

<div class="field">
    <label class="field__label" for="agreement-details">Agreement details — optional</label>
    <input
        class="input"
        type="text"
        id="agreement-details"
        name="agreement_details"
        value="<?= e($draft['agreement_details']) ?>"
        placeholder="CSR agreement ref, contribution schedule"
        <?= isset($errors['agreement_details']) ? 'aria-invalid="true"' : '' ?>
    >
    <?= field_error($errors, 'agreement_details') ?>
</div>

<div class="field">
    <label class="field__label" for="internal-notes">Internal notes — optional</label>
    <textarea
        class="textarea"
        id="internal-notes"
        name="internal_notes"
        rows="3"
        placeholder="Notes about this sponsor (visible to liaisons only)"
        <?= isset($errors['internal_notes']) ? 'aria-invalid="true"' : '' ?>
    ><?= e($draft['internal_notes']) ?></textarea>
    <?= field_error($errors, 'internal_notes') ?>
</div>
