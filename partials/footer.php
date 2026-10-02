<?php

declare(strict_types=1);

/**
 * Closing page chrome. Counterpart to partials/header.php.
 *
 * A view that needs behaviour sets $pageScripts before including this, e.g.
 * $pageScripts = ['modal.js']; JS only ever enhances — every form here works
 * without it.
 *
 * @var array $pageScripts file names under /public/js
 */

// polling.js keeps the bell's unread count current on every page (§21.4).
$pageScripts = array_values(array_unique(array_merge($pageScripts ?? [], ['polling.js'])));

?>
</main>
<script src="<?= e(asset_url('js/navigation.js')) ?>"></script>
<?php foreach ($pageScripts as $script): ?>
<script src="<?= e(asset_url('js/' . $script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
