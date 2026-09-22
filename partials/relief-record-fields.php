<?php

declare(strict_types=1);

/**
 * A relief record's fields, shared by the create and edit forms so the
 * wording never drifts between the two.
 *
 * @var array $draft       current field values
 * @var array $errors      per-field messages
 * @var array $reliefTypes value => label
 * @var array $sponsors    rows: id, company_name
 */

?>
<div class="field">
    <label class="field__label" for="relief-type">Relief type</label>
    <select
        class="input"
        id="relief-type"
        name="relief_type"
        <?= isset($errors['relief_type']) ? 'aria-invalid="true"' : '' ?>
    >
        <option value="">Select type</option>
        <?php foreach ($reliefTypes as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $draft['relief_type'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <?= field_error($errors, 'relief_type') ?>
</div>

<div class="field">
    <label class="field__label" for="description">What was given</label>
    <input
        class="input"
        type="text"
        id="description"
        name="description"
        value="<?= e($draft['description']) ?>"
        placeholder="40 dry-ration packs and 80 litres of drinking water"
        <?= isset($errors['description']) ? 'aria-invalid="true"' : '' ?>
    >
    <?= field_error($errors, 'description') ?>
</div>

<div class="field">
    <label class="field__label" for="location">Location</label>
    <input
        class="input"
        type="text"
        id="location"
        name="location"
        value="<?= e($draft['location']) ?>"
        placeholder="Temple Road community hall"
        <?= isset($errors['location']) ? 'aria-invalid="true"' : '' ?>
    >
    <?= field_error($errors, 'location') ?>
</div>

<div class="field">
    <label class="field__label" for="households-reached">Households reached</label>
    <input
        class="input"
        type="number"
        id="households-reached"
        name="households_reached"
        value="<?= e($draft['households_reached']) ?>"
        placeholder="12"
        <?= isset($errors['households_reached']) ? 'aria-invalid="true"' : '' ?>
    >
    <?= field_error($errors, 'households_reached') ?>
</div>

<div class="field">
    <label class="field__label" for="distributed-on">Date handed out</label>
    <input
        class="input input--date"
        type="date"
        id="distributed-on"
        name="distributed_on"
        value="<?= e($draft['distributed_on']) ?>"
        <?= isset($errors['distributed_on']) ? 'aria-invalid="true"' : '' ?>
    >
    <?= field_error($errors, 'distributed_on') ?>
</div>

<div class="field">
    <label class="field__label" for="sponsor-id">Sponsor who provided it — optional</label>
    <select
        class="input"
        id="sponsor-id"
        name="sponsor_id"
        <?= isset($errors['sponsor_id']) ? 'aria-invalid="true"' : '' ?>
    >
        <option value="">Not from a sponsor, or not known</option>
        <?php foreach ($sponsors as $sponsor): ?>
            <option value="<?= e((string) $sponsor['id']) ?>"<?= $draft['sponsor_id'] === (string) $sponsor['id'] ? ' selected' : '' ?>><?= e((string) $sponsor['company_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?= field_error($errors, 'sponsor_id') ?>
</div>

<div class="field">
    <label class="field__label" for="estimated-value">Estimated value in LKR — optional</label>
    <input
        class="input"
        type="number"
        id="estimated-value"
        name="estimated_value"
        value="<?= e($draft['estimated_value']) ?>"
        placeholder="25000"
        <?= isset($errors['estimated_value']) ? 'aria-invalid="true"' : '' ?>
    >
    <span class="field__hint">For the relief report only. No points move.</span>
    <?= field_error($errors, 'estimated_value') ?>
</div>

<div class="field">
    <label class="field__label" for="notes">Notes — optional</label>
    <textarea
        class="textarea"
        id="notes"
        name="notes"
        rows="3"
        placeholder="Anything the Admin or Sponsor Liaison should know"
        <?= isset($errors['notes']) ? 'aria-invalid="true"' : '' ?>
    ><?= e($draft['notes']) ?></textarea>
    <?= field_error($errors, 'notes') ?>
</div>
