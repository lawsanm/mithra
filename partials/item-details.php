<?php

declare(strict_types=1);

/**
 * Item name, category and description, shared by the create wizard and the
 * edit form so the limits and wording never drift between the two.
 *
 * @var array $draft      current field values
 * @var array $errors     per-field messages
 * @var array $categories rows from item_categories: id, name
 */

?>
<div class="field">
    <label class="field__label" for="item-name">Item name</label>
    <input
        class="input"
        type="text"
        id="item-name"
        name="name"
        value="<?= e((string) $draft['name']) ?>"
        placeholder="e.g. Bosch Cordless Drill GSB 120"
    >
    <?= field_error($errors, 'name') ?>
</div>

<div class="field">
    <label class="field__label" for="item-category">Category</label>
    <select class="input" id="item-category" name="category">
        <option value="">Select category</option>
        <?php foreach ($categories as $category): ?>
            <option
                value="<?= e((string) $category['id']) ?>"
                <?= (string) $draft['category'] === (string) $category['id'] ? 'selected' : '' ?>
            ><?= e((string) $category['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?= field_error($errors, 'category') ?>
</div>

<div class="field">
    <label class="field__label" for="item-description">Description — optional</label>
    <textarea
        class="input"
        id="item-description"
        name="description"
        rows="4"
        placeholder="Condition, what is included, anything a borrower should know."
    ><?= e((string) $draft['description']) ?></textarea>
    <span class="field__hint">Borrowers search this text, so name the brand and the accessories.</span>
    <?= field_error($errors, 'description') ?>
</div>
