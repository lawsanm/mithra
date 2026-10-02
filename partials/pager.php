<?php

declare(strict_types=1);

/**
 * The Previous / Next links under a paged list. Lists shown newest first pass
 * $pagerLabels = ['Newer', 'Older'].
 *
 * @var int                          $page        1-based page number
 * @var bool                         $hasNextPage
 * @var callable(int): string        $pageUrl     the list's URL for another page
 * @var array{0: string, 1: string}  $pagerLabels optional
 */

[$previousLabel, $nextLabel] = $pagerLabels ?? ['Previous', 'Next'];

?>
<?php if ($page > 1 || $hasNextPage): ?>
    <div class="actions">
        <?php if ($page > 1): ?>
            <a class="btn btn--ghost" href="<?= e($pageUrl($page - 1)) ?>"><?= e($previousLabel) ?></a>
        <?php endif; ?>
        <?php if ($hasNextPage): ?>
            <a class="btn btn--ghost" href="<?= e($pageUrl($page + 1)) ?>"><?= e($nextLabel) ?></a>
        <?php endif; ?>
    </div>
<?php endif; ?>
