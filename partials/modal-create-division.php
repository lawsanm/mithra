<?php

declare(strict_types=1);

/**
 * Create division modal — the Admin adds a GN division. It is active at once,
 * so residents can register against it straight away.
 */

?>
<dialog aria-labelledby="modal-create-division-title" class="modal modal--sm" id="modal-create-division">
    <form action="<?= base_url() ?>/admin/divisions" method="post">
        <?= csrf_field() ?>

        <div class="modal__head">
            <h2 class="modal__title" id="modal-create-division-title">Create new division</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <p class="page-intro__meta" style="margin-bottom:var(--space-4);">A division maps to one GN division. New members register against it and its moderator handles first-line disputes.</p>

        <div class="field">
            <label class="field__label" for="division_name">Division name</label>
            <input class="input" id="division_name" name="name" type="text" maxlength="120" placeholder="e.g. Wellawatte South" required>
        </div>

        <div class="field">
            <label class="field__label" for="district">District</label>
            <input class="input" id="district" name="district" type="text" maxlength="100" placeholder="Colombo" required>
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Create division</button>
        </div>
    </form>
</dialog>
