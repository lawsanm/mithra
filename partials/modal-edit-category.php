<?php

declare(strict_types=1);

/**
 * Edit / Add category modal. */

?>
<dialog aria-labelledby="category-modal-title" class="modal modal--sm" id="modal-edit-category"<?= $categoryModalOpen ? ' open' : '' ?>>
    <form id="form-edit-category" class="stack" method="post" action="<?= base_url() ?>/admin/categories" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="category_id" value="<?= e($categoryDraft['category_id'] ?? '0') ?>" data-modal-field="categoryId">
        <div class="modal__head">
            <h2 class="modal__title" id="category-modal-title" data-modal-field="modalTitle"><?= ($categoryDraft['category_id'] ?? '0') === '0' ? 'Add category' : 'Edit category' ?></h2>
            <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
        </div>

        <div class="field">
            <label class="field__label" for="category_name">Category name</label>
            <input class="input" id="category_name" name="name" type="text" value="<?= e($categoryDraft['name'] ?? '') ?>" data-modal-field="categoryName" aria-invalid="<?= isset($categoryErrors['name']) ? 'true' : 'false' ?>" aria-describedby="category-name-error" placeholder="e.g. Sports & Outdoor">
            <span id="category-name-error"><?= field_error($categoryErrors, 'name') ?></span>
        </div>

        <div class="field">
            <label class="field__label" for="category_visibility">Visibility</label>
            <select class="input" id="category_visibility" name="visibility" data-modal-field="categoryVisibility" aria-invalid="<?= isset($categoryErrors['visibility']) ? 'true' : 'false' ?>" aria-describedby="category-visibility-error">
                <option value="active"<?= ($categoryDraft['visibility'] ?? 'active') === 'active' ? ' selected' : '' ?>>Active</option>
                <option value="hidden"<?= ($categoryDraft['visibility'] ?? '') === 'hidden' ? ' selected' : '' ?>>Hidden</option>
            </select>
            <span id="category-visibility-error"><?= field_error($categoryErrors, 'visibility') ?></span>
        </div>

        <div class="modal__footer">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancel</button>
            <button class="btn btn--primary" type="submit">Save category</button>
        </div>
    </form>
</dialog>
