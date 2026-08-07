<?php

declare(strict_types=1);

/**
 * Dismiss objection confirmation modal.
 */

?>
<dialog class="modal" id="dismiss-modal">
    <form method="post" action="/admin/moderators/objections/dismiss" class="modal__content">
        <?= csrf_field() ?>
        <div class="modal__head">
            <h2 class="modal__title">Dismiss objection</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Close">✕</button>
        </div>

        <p style="font-size: var(--text-ui-label); margin-bottom: var(--space-4);">
            Are you sure? The objection raised by <strong id="dismiss-member-name"></strong> will be marked as invalid and the appointment continues.
        </p>

        <div class="field" style="margin-bottom: var(--space-4);">
            <label class="field__label" for="dismiss-reason">Reason for dismissal</label>
            <textarea class="input" id="dismiss-reason" name="reason" rows="3" required placeholder="Explain why this objection is invalid…"></textarea>
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Dismiss objection</button>
        </div>
    </form>
</dialog>
