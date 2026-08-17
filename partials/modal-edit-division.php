<?php

declare(strict_types=1);

/**
 * Edit division modal — admin updates name/district for an existing
 * division. JS fills the hidden fields and text inputs before opening
 * (same pattern as modal-edit-category.php).
 */

?>
<dialog class="modal modal--sm" id="modal-edit-division">
    <form method="post" action="/admin/divisions" id="form-edit-division">
        <?= csrf_field() ?>

        <div class="modal__head">
            <h2 class="modal__title">Edit division</h2>
            <button class="modal__close" type="button" aria-label="Close">✕</button>
        </div>

        <div class="field">
            <label class="field__label" for="edit_division_name">Division name</label>
            <input class="input" id="edit_division_name" name="name" type="text" required>
        </div>

        <div class="field">
            <label class="field__label" for="edit_district">District</label>
            <input class="input" id="edit_district" name="district" type="text" required>
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Save changes</button>
        </div>
    </form>
</dialog>
