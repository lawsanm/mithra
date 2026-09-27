<?php

declare(strict_types=1);

/**
 * Create division modal — the Admin adds a GN division. It is active at once,
 * so residents can register against it straight away.
 */

?>
<dialog aria-labelledby="modal-create-division-title" class="modal modal--sm" id="modal-create-division">
    <form action="<?= base_url() ?>/admin/divisions" method="post" novalidate>
        <?= csrf_field() ?>

        <div class="modal__head">
            <h2 class="modal__title" id="modal-create-division-title">Create new division</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <p class="page-intro__meta u-mb-4">A division maps to one GN division. New members register against it and its moderator handles first-line disputes.</p>

        <?php
        $fieldPrefix      = 'create_';
        $selectedProvince = '';
        $selectedDistrict = '';
        include __DIR__ . '/division-location-fields.php';
        ?>

        <div class="field">
            <label class="field__label" for="division_name">Division name</label>
            <input class="input" id="division_name" name="name" type="text" placeholder="e.g. Wellawatte South">
        </div>

        <div class="field">
            <label class="field__label" for="postal_code">Postal code</label>
            <input class="input" id="postal_code" name="postal_code" type="text" inputmode="numeric" placeholder="e.g. 00600">
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Create division</button>
        </div>
    </form>
</dialog>
