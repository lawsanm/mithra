<?php

declare(strict_types=1);

/**
 * Activate Disaster Mode modal.
 *
 * @var array $divisions  id, name pairs for the select
 */

$divisions ??= [];

?>
<dialog aria-labelledby="modal-activate-disaster-title" class="modal modal--sm" id="modal-activate-disaster">
    <div data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
        

        <div class="modal__head">
            <h2 class="modal__title" id="modal-activate-disaster-title">Activate Disaster Mode</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <div class="field">
            <label class="field__label" for="disaster_division">Division</label>
            <select class="input" id="disaster_division" data-modal-field="divisionId" name="division_id" required disabled>
                <option value="">Select division...</option>
                <?php foreach ($divisions as $d): ?>
                    <option value="<?= e((string) $d['id']) ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="field__label" for="disaster_type">Disaster type</label>
            <select class="input" id="disaster_type" name="type" required disabled>
                <option value="">Select type...</option>
                <option value="flooding">Flooding</option>
                <option value="fire">Fire</option>
                <option value="storm">Storm / cyclone</option>
                <option value="earthquake">Earthquake</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="field">
            <label class="field__label" for="disaster_end">End date</label>
            <input class="input" id="disaster_end" name="end_date" type="date" required disabled>
        </div>

        <div class="field">
            <label class="field__label" for="disaster_notes">Notes (optional)</label>
            <textarea class="input" id="disaster_notes" name="notes" rows="3" placeholder="Situation details, affected areas..." disabled></textarea>
        </div>

        <div class="notice notice--warning notice--full">
            Activating Disaster Mode fast-tracks aid vouching, alerts sponsors, and relaxes late fees in the affected division.
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit" disabled>Activate</button>
        </div>
    </div>
</dialog>
