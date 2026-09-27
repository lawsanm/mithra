<?php

declare(strict_types=1);

/** @var ?string $photoUrl @var string $photoTitle @var string $photoClass */
?>
<?php if ($photoUrl !== null && $photoUrl !== ''): ?>
    <img class="thumb thumb__img thumb--item <?= e($photoClass) ?>" src="<?= e($photoUrl) ?>" alt="<?= e($photoTitle) ?>" loading="lazy" decoding="async">
<?php else: ?>
    <span class="thumb <?= e($photoClass) ?>" role="img" aria-label="No photo available for <?= e($photoTitle) ?>">No photo</span>
<?php endif; ?>
