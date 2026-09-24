<?php

declare(strict_types=1);

/**
 * A disaster contribution's fields — what the sponsor says they gave and the
 * proof they sent the Liaison. Shared by the record and edit forms so the
 * wording never drifts between the two. Preview only: every input is disabled
 * until the Liaison's contribution module lands (Plan §14.3).
 *
 * @var array $draft     current field values
 * @var array $errors    per-field messages
 * @var array $disasters rows: id, title (active or recently ended events)
 * @var array $sponsors  rows: id, name
 */

?>
<div class="field-row">
    <div class="field">
        <label class="field__label" for="disaster-event-id">Disaster</label>
        <select class="input" id="disaster-event-id" name="disaster_event_id" disabled>
            <option value="">Select disaster</option>
            <?php foreach ($disasters as $disasterOption): ?>
                <option value="<?= e((string) $disasterOption['id']) ?>"<?= (string) $draft['disaster_event_id'] === (string) $disasterOption['id'] ? ' selected' : '' ?>>
                    <?= e($disasterOption['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="field__hint">The division's Moderator is the recipient and is asked to confirm.</span>
        <?= field_error($errors, 'disaster_event_id') ?>
    </div>

    <div class="field">
        <label class="field__label" for="sponsor-id">Sponsor</label>
        <select class="input" id="sponsor-id" name="sponsor_id" disabled>
            <option value="">Select sponsor</option>
            <?php foreach ($sponsors as $sponsor): ?>
                <option value="<?= e((string) $sponsor['id']) ?>"<?= (string) $draft['sponsor_id'] === (string) $sponsor['id'] ? ' selected' : '' ?>>
                    <?= e($sponsor['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'sponsor_id') ?>
    </div>
</div>

<p class="form-card__legend">What the sponsor gave</p>

<label class="choice">
    <input class="choice__input" type="radio" name="contribution_kind" value="cash"<?= $draft['contribution_kind'] === 'cash' ? ' checked' : '' ?> disabled>
    <span class="choice__body">
        <span class="choice__title">Cash</span>
        <span class="choice__note">Money handed or transferred to the Moderator for relief work.</span>
    </span>
</label>

<label class="choice">
    <input class="choice__input" type="radio" name="contribution_kind" value="goods"<?= $draft['contribution_kind'] === 'goods' ? ' checked' : '' ?> disabled>
    <span class="choice__body">
        <span class="choice__title">Goods</span>
        <span class="choice__note">Rations, water, tarpaulins, medicine or other supplies delivered to the Moderator.</span>
    </span>
</label>

<div class="field">
    <label class="field__label" for="description">Description</label>
    <input
        class="input"
        type="text"
        id="description"
        name="description"
        value="<?= e($draft['description']) ?>"
        placeholder="40 dry-ration packs (rice, dhal, sugar, tea)"
        disabled
    >
    <?= field_error($errors, 'description') ?>
</div>

<div class="field-row">
    <div class="field">
        <label class="field__label" for="estimated-value">Value (LKR)</label>
        <input
            class="input input--half"
            type="number"
            id="estimated-value"
            name="estimated_value"
            value="<?= e($draft['estimated_value']) ?>"
            placeholder="48,000"
            disabled
        >
        <span class="field__hint">The cash amount, or the goods' value from the sponsor's invoice.</span>
        <?= field_error($errors, 'estimated_value') ?>
    </div>

    <div class="field">
        <label class="field__label" for="handed-over-on">Date handed over</label>
        <input
            class="input input--date"
            type="date"
            id="handed-over-on"
            name="handed_over_on"
            value="<?= e($draft['handed_over_on']) ?>"
            disabled
        >
        <?= field_error($errors, 'handed_over_on') ?>
    </div>
</div>

<p class="form-card__legend">Sponsor's proof</p>

<div class="field">
    <label class="field__label" for="receipt-reference">Sponsor's receipt reference</label>
    <input
        class="input input--half"
        type="text"
        id="receipt-reference"
        name="receipt_reference"
        value="<?= e($draft['receipt_reference']) ?>"
        placeholder="NW-DN-2231"
        disabled
    >
    <span class="field__hint">Bank transfer slip, supplier invoice or delivery note number.</span>
    <?= field_error($errors, 'receipt_reference') ?>
</div>

<label class="upload-drop">
    <span class="upload-drop__glyph" aria-hidden="true">＋</span>
    <span>Upload the sponsor's proof — PDF or photo, up to 3 files</span>
    <input class="visually-hidden" type="file" name="sponsor_proof[]" accept="image/*,application/pdf" multiple disabled>
</label>

<div class="field">
    <label class="field__label" for="notes">Notes (optional)</label>
    <textarea class="textarea" id="notes" name="notes" rows="2" placeholder="How the sponsor sent the proof, contact person…" disabled><?= e($draft['notes']) ?></textarea>
</div>
