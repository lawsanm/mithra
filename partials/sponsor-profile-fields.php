<?php

declare(strict_types=1);

/**
 * A sponsor's company, contact and agreement fields, shared by onboarding and
 * the edit form so the wording never drifts between the two.
 *
 * @var array $draft             current field values
 * @var array $errors            per-field messages
 * @var array $agreementStatuses value => label
 * @var array $accounts          sponsor login accounts that may be linked: id, full_name, email
 * @var array|null $sponsor      the company being edited (id, name, active, linked), null when onboarding
 */

$accounts = $accounts ?? [];
$sponsor  = $sponsor ?? null;

?>
<div class="field">
    <label class="field__label" for="sponsor-account">Sponsor login account</label>
    <select
        class="input"
        id="sponsor-account"
        name="user_id"
        <?= isset($errors['user_id']) ? 'aria-invalid="true"' : '' ?>
    >
        <?php if ($sponsor === null): ?>
            <option value="">Select a sponsor account</option>
        <?php elseif (!$sponsor['linked']): ?>
            <option value="">Not linked yet</option>
        <?php endif; ?>
        <?php foreach ($accounts as $account): ?>
            <option value="<?= e((string) $account['id']) ?>"<?= $draft['user_id'] === (string) $account['id'] ? ' selected' : '' ?>>
                <?= e((string) $account['full_name']) ?><?= $account['email'] !== null ? ' · ' . e((string) $account['email']) : '' ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if ($accounts === []): ?>
        <span class="field__hint">
            No sponsor account is free. Each company is linked to its own sponsor login, so one must
            exist before the company can be onboarded.
        </span>
    <?php else: ?>
        <span class="field__hint">Only active sponsor accounts not linked to another company are listed.</span>
    <?php endif; ?>
    <?= field_error($errors, 'user_id') ?>
</div>

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
    <label class="field__label" for="contact-person">Contact person — optional</label>
    <input
        class="input"
        type="text"
        id="contact-person"
        name="contact_person"
        value="<?= e($draft['contact_person']) ?>"
        placeholder="T.H.K. Madushan"
        <?= isset($errors['contact_person']) ? 'aria-invalid="true"' : '' ?>
    >
    <?= field_error($errors, 'contact_person') ?>
</div>

<div class="field">
    <label class="field__label" for="contact-email">Contact email — optional</label>
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
