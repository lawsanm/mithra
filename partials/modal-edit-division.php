<?php

declare(strict_types=1);

/**
 * Edit division modal — the Admin corrects a division's province, district,
 * name or postal code.
 *
 * @var array $division id, province, district, name, postal_code
 */

?>
<dialog aria-labelledby="modal-edit-division-title" class="modal modal--sm" id="modal-edit-division">
    <form action="<?= base_url() ?>/admin/divisions/<?= e((string) $division['id']) ?>" method="post" novalidate>
        <?= csrf_field() ?>

        <div class="modal__head">
            <h2 class="modal__title" id="modal-edit-division-title">Edit division</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <p class="page-intro__meta u-mb-4">Update the division's location, name or postal code.</p>

        <?php
        $fieldPrefix      = 'edit_';
        $selectedProvince = $division['province'];
        $selectedDistrict = $division['district'];
        include __DIR__ . '/division-location-fields.php';
        ?>

        <div class="field" style="margin-top: var(--space-3);">
            <label class="field__label" for="edit_division_name">Division name</label>
            <input class="input" id="edit_division_name" name="name" type="text" value="<?= e($division['name']) ?>">
        </div>

        <div class="field" style="margin-top: var(--space-3);">
            <label class="field__label" for="edit_postal_code">Postal code</label>
            <input class="input" id="edit_postal_code" name="postal_code" type="text" inputmode="numeric" value="<?= e($division['postal_code']) ?>">
        </div>

        <div class="modal__footer" style="margin-top: var(--space-4);">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Save changes</button>
        </div>
    </form>
</dialog>
