<?php

declare(strict_types=1);

/**
 * Uphold objection — cancel appointment confirmation modal.
 */

?>
<dialog class="modal" id="uphold-modal">
    <form method="post" action="/admin/moderators/objections/uphold" class="modal__content">
        <?= csrf_field() ?>
        <div class="modal__head">
            <h2 class="modal__title">Uphold objection — cancel appointment</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Close">✕</button>
        </div>

        <p style="font-size: var(--text-ui-label); margin-bottom: var(--space-4);">
            This will cancel the appointment of <strong id="uphold-appointee-name"></strong>. The division will need a new moderator selection.
        </p>

        <div class="notice notice--warning" style="margin-bottom: var(--space-4);">
            Upholding this objection permanently cancels the current appointment. The objection window closes immediately and the division returns to the moderator selection process.
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--danger" type="submit">Uphold and cancel appointment</button>
        </div>
    </form>
</dialog>
