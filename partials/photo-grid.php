<?php

declare(strict_types=1);

/**
 * Stored photos or documents as thumbnails, each linking to the full image
 * through the access-checked proxy.
 *
 * @var array $gridPhotos rows: url, label
 */

?>
<div class="photo-grid">
    <?php foreach ($gridPhotos as $gridPhoto): ?>
        <a class="link" href="<?= e($gridPhoto['url']) ?>">
            <img class="thumb thumb--photo thumb__img thumb--item" src="<?= e($gridPhoto['url']) ?>" alt="<?= e($gridPhoto['label']) ?>" loading="lazy" decoding="async">
            <?= e($gridPhoto['label']) ?>
        </a>
    <?php endforeach; ?>
</div>
