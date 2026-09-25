<?php

declare(strict_types=1);

/**
 * Declared value and the kind of proof offered for it (Plan §9.1), shared by
 * the create wizard and the edit form. The tier wording comes from
 * ItemService, the one place the thresholds are defined.
 *
 * @var array $draft      current field values
 * @var array $errors     per-field messages
 * @var array $proofTypes value => label for the proof-of-value kinds
 */

?>
<div class="field">
    <label class="field__label" for="declared-value">Declared value (pts)</label>
    <input
        class="input input--narrow"
        type="number"
        id="declared-value"
        name="declared_value"
        value="<?= e((string) $draft['declared_value']) ?>"
    >
    <span class="field__hint">Used to size the security hold and cap any damage claim.</span>
    <?= field_error($errors, 'declared_value') ?>
</div>

<div class="field">
    <label class="field__label" for="value-proof-type">Proof of value</label>
    <select class="input" id="value-proof-type" name="value_proof_type">
        <option value="">None — declared value is <?= number_format(ItemService::PROOF_FREE_UP_TO) ?> points or less</option>
        <?php foreach ($proofTypes as $proofValue => $proofLabel): ?>
            <option
                value="<?= e($proofValue) ?>"
                <?= (string) ($draft['value_proof_type'] ?? '') === $proofValue ? 'selected' : '' ?>
            ><?= e($proofLabel) ?></option>
        <?php endforeach; ?>
    </select>
    <span class="field__hint">
        <?= e(ItemService::proofRequirement(ItemService::PROOF_FREE_UP_TO)) ?>
        <?= e(ItemService::proofRequirement(ItemService::DOCUMENT_UP_TO)) ?>
        <?= e(ItemService::proofRequirement(ItemService::DOCUMENT_UP_TO + 1)) ?>
    </span>
    <?= field_error($errors, 'value_proof_type') ?>
</div>
