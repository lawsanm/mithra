<?php

declare(strict_types=1);

/**
 * Deactivate Disaster Mode modal. */

?>
<dialog aria-labelledby="modal-deactivate-disaster-title" class="modal modal--sm" id="modal-deactivate-disaster">
    <div data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
        
        

        <div class="modal__head">
            <h2 class="modal__title" id="modal-deactivate-disaster-title">Deactivate Disaster Mode</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <p class="list-row__meta" style="margin-bottom:var(--space-4);">You are about to deactivate Disaster Mode for <strong id="deactivate-division-name" data-modal-field="divisionName"></strong>.</p>

        <p class="list-row__meta" style="margin-bottom:var(--space-4);">Late-fee relaxation and aid fast-tracking will end immediately. Existing vouched aid already in progress will not be reversed.</p>

        <div class="notice notice--warning notice--full">
            This action is logged and visible to sponsors and moderators.
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit" disabled>Deactivate</button>
        </div>
    </div>
</dialog>
