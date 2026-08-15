<?php

declare(strict_types=1);

/**
 * Appoint moderator confirmation modal.
 */

?>
<dialog class="modal modal--sm" id="modal-appoint-moderator">
    <form method="post" action="<?= base_url() ?>/admin/moderators/appoint">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" id="appoint-user-id">
        <input type="hidden" name="division_id" id="appoint-division-id">

        <div class="modal__head">
            <h2 class="modal__title">Appoint moderator</h2>
            <button class="modal__close" type="button" aria-label="Close">✕</button>
        </div>

        <p class="list-row__meta" style="margin-bottom:var(--space-4);">You are about to appoint <strong id="appoint-candidate-name"></strong> as moderator for <strong id="appoint-division-name"></strong>.</p>

        <div class="field">
            <label class="field__label" for="conduct_bond">Conduct bond (pts)</label>
            <input class="input" id="conduct_bond" name="conduct_bond" type="number" min="0" value="500">
        </div>

        <div class="field">
            <label class="field__label" for="effective_date">Effective date</label>
            <input class="input" id="effective_date" name="effective_date" type="date">
        </div>

        <div class="notice notice--warning notice--full">
            The conduct bond is held in escrow for the moderator's tenure. If they are removed for misconduct, the bond is forfeited.
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Confirm appointment</button>
        </div>
    </form>
</dialog>
