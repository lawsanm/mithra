<?php

declare(strict_types=1);

/**
 * Edit / Add category modal. */

?>
<dialog class="modal modal--sm" id="modal-edit-category">
    <form method="post" action="/admin/categories" id="form-edit-category">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="category-id">

        <div class="modal__head">
            <h2 class="modal__title" id="category-modal-title">Add category</h2>
            <button class="modal__close" type="button" aria-label="Close">✕</button>
        </div>

        <div class="field">
            <label class="field__label" for="category_name">Category name</label>
            <input class="input" id="category_name" name="name" type="text" placeholder="e.g. Sports & Outdoor" required>
        </div>

        <div class="field">
            <label class="field__label" for="category_visibility">Visibility</label>
            <select class="input" id="category_visibility" name="visibility">
                <option value="active">Active</option>
                <option value="hidden">Hidden</option>
            </select>
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" onclick="this.closest('dialog').close()">Cancel</button>
            <button class="btn btn--primary" type="submit">Save category</button>
        </div>
    </form>
</dialog>
