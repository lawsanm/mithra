<?php

declare(strict_types=1);

/**
 * One-shot confirmation banner. The controller reads and clears the flash, then
 * hands it to the view; nothing renders when there is none.
 *
 * @var array{type: string, message: string}|null $flash
 */

$flash = $flash ?? null;

if ($flash === null) {
    return;
}

$flashClass = $flash['type'] === 'error' ? 'error' : 'success';

?>
<p class="notice notice--<?= e($flashClass) ?>" role="status">
    <?= e($flash['message']) ?>
</p>
