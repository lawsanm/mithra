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

$pageScripts = $pageScripts ?? [];

?>
</main>
<?php foreach ($pageScripts as $script): ?>
<script src="<?= base_url() ?>/js/<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
