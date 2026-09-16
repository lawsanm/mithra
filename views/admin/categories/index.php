<?php

declare(strict_types=1);

/**
 * Category management — admin CRUD for item categories.
 *
 * @var array $categories  rows: id, name, listing_count, status, status_label
 */

$pageTitle = 'Category management';
$navActive = 'categories';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Category management</h1>
    <button class="btn btn--primary page-header__action" type="button" data-modal-open="modal-edit-category" data-mode="create">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
        Add category
    </button>
</header>

<ul class="row-list">
    <?php foreach ($categories as $cat): ?>
        <li class="list-row">
            <div class="list-row__body">
                <span class="list-row__title"><?= e($cat['name']) ?></span>
                <span class="list-row__meta"><?= e(number_format($cat['listing_count'])) ?> listings</span>
            </div>
            <span class="badge badge--<?= e($cat['status']) ?>"><?= e($cat['status_label']) ?></span>
            <button class="btn btn--ghost" type="button" data-modal-open="modal-edit-category" data-category-id="<?= e((string) $cat['id']) ?>" data-category-name="<?= e($cat['name']) ?>">Edit</button>
        </li>
    <?php endforeach; ?>
</ul>

<div class="notice notice--info notice--full">
    Hiding a category keeps existing listings but blocks new ones.
</div>

<?php include __DIR__ . '/../../../partials/modal-edit-category.php'; ?>

<?php $pageScripts = ['modal.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
