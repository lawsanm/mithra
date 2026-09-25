<?php

declare(strict_types=1);

/**
 * Edit division modal — the Admin renames a division or corrects its district.
 *
 * @var array $division id, name, district
 */

?>
<dialog aria-labelledby="modal-edit-division-title" class="modal modal--sm" id="modal-edit-division">
    <form action="<?= base_url() ?>/admin/divisions/<?= e((string) $division['id']) ?>" method="post" novalidate>
        <?= csrf_field() ?>

        <div class="modal__head">
            <h2 class="modal__title" id="modal-edit-division-title">Edit division</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <p class="page-intro__meta u-mb-4">Update division name or district information.</p>

        <div class="field">
            <label class="field__label" for="edit_division_name">Division name</label>
            <input class="input" id="edit_division_name" name="name" type="text" value="<?= e($division['name']) ?>">
        </div>

        <div class="field" style="margin-top: var(--space-3);">
            <label class="field__label" for="edit_district">District</label>
            <input class="input" id="edit_district" name="district" type="text" value="<?= e($division['district']) ?>">
        </div>

        <div class="modal__footer" style="margin-top: var(--space-4);">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Save changes</button>
        </div>
    </form>
</dialog>
