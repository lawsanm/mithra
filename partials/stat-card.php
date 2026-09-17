<?php

declare(strict_types=1);

/**
 * One stat tile. A tile with an href is a link to the page behind the number.
 *
 * @var array  $stat     label, value, and optionally note, href, tone
 * @var string $statTone value colour when $stat has no tone of its own:
 *                       '', primary, accent, warning, success or error
 */

$tone = (string) ($stat['tone'] ?? $statTone ?? '');
$tag  = isset($stat['href']) ? 'a' : 'div';

?>
<<?= e($tag) ?> class="stat-card"<?= isset($stat['href']) ? ' href="' . e((string) $stat['href']) . '"' : '' ?>>
    <span class="stat-card__label"><?= e((string) $stat['label']) ?></span>
    <strong class="stat-card__value<?= $tone !== '' ? ' stat-card__value--' . e($tone) : '' ?>"><?= e((string) $stat['value']) ?></strong>
    <?php if (isset($stat['note'])): ?>
        <span class="stat-card__note"><?= e((string) $stat['note']) ?></span>
    <?php endif; ?>
</<?= e($tag) ?>>
