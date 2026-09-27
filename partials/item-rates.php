<?php

declare(strict_types=1);

/**
 * Daily and monthly rate, shared by the create wizard and the edit form.
 *
 * @var array $draft  current field values
 * @var array $errors per-field messages
 */

?>
<div class="field-row">
    <div class="field">
        <label class="field__label" for="daily-rate">Daily rate (pts)</label>
        <input
            class="input input--narrow"
            type="number"
            id="daily-rate"
            name="daily_rate"
            value="<?= e((string) $draft['daily_rate']) ?>"
        >
        <?= field_error($errors, 'daily_rate') ?>
    </div>
    <div class="field">
        <label class="field__label" for="monthly-rate">Monthly rate (pts)</label>
        <input
            class="input input--narrow"
            type="number"
            id="monthly-rate"
            name="monthly_rate"
            value="<?= e((string) $draft['monthly_rate']) ?>"
        >
        <?= field_error($errors, 'monthly_rate') ?>
    </div>
</div>
