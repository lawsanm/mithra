<?php

declare(strict_types=1);

/**
 * Appoint moderator confirmation modal.
 */

?>
<dialog aria-labelledby="modal-appoint-moderator-title" class="modal modal--sm" id="modal-appoint-moderator">
    <div data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
        
        
        

        <div class="modal__head">
            <h2 class="modal__title" id="modal-appoint-moderator-title">Appoint moderator</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <p class="list-row__meta" style="margin-bottom:var(--space-4);">You are about to appoint <strong id="appoint-candidate-name"></strong> as moderator for <strong id="appoint-division-name"></strong>.</p>

        <div class="field">
            <label class="field__label" for="conduct_bond">Conduct bond (pts)</label>
            <input class="input" id="conduct_bond" name="conduct_bond" type="number" min="0" value="500" disabled>
        </div>

        <div class="field">
            <label class="field__label" for="effective_date">Effective date</label>
            <input class="input" id="effective_date" name="effective_date" type="date" disabled>
        </div>

        <div class="notice notice--warning notice--full">
            The conduct bond is held in escrow for the moderator's tenure. If they are removed for misconduct, the bond is forfeited.
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit" disabled>Confirm appointment</button>
        </div>
    </div>
</dialog>
