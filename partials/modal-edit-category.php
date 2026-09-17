<?php

declare(strict_types=1);

/**
 * Edit / Add category modal. */

?>
<dialog aria-labelledby="category-modal-title" class="modal modal--sm" id="modal-edit-category">
    <div id="form-edit-category" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
        
        

        <div class="modal__head">
            <h2 class="modal__title" id="category-modal-title">Add category</h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <div class="field">
            <label class="field__label" for="category_name">Category name</label>
            <input class="input" id="category_name" name="name" type="text" placeholder="e.g. Sports & Outdoor" required disabled>
        </div>

        <div class="field">
            <label class="field__label" for="category_visibility">Visibility</label>
            <select class="input" id="category_visibility" name="visibility" disabled>
                <option value="active">Active</option>
                <option value="hidden">Hidden</option>
            </select>
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit" disabled>Save category</button>
        </div>
    </div>
</dialog>
