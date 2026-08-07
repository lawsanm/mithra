<?php

declare(strict_types=1);

/**
 * Deactivate Disaster Mode modal. */

?>
<dialog class="modal modal--sm" id="modal-deactivate-disaster">
    <form method="post" action="/admin/disaster/deactivate">
        <?= csrf_field() ?>
        <input type="hidden" name="division_id" id="deactivate-division-id">

        <div class="modal__head">
            <h2 class="modal__title">Deactivate Disaster Mode</h2>
            <button class="modal__close" type="button" aria-label="Close">✕</button>
        </div>

        <p class="list-row__meta" style="margin-bottom:var(--space-4);">You are about to deactivate Disaster Mode for <strong id="deactivate-division-name"></strong>.</p>

        <p class="list-row__meta" style="margin-bottom:var(--space-4);">Late-fee relaxation and aid fast-tracking will end immediately. Existing vouched aid already in progress will not be reversed.</p>

        <div class="notice notice--warning notice--full">
            This action is logged and visible to sponsors and moderators.
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Deactivate</button>
        </div>
    </form>
</dialog>
