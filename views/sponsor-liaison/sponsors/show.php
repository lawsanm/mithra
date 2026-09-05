<?php

declare(strict_types=1);

$pageTitle = $record['name'];
$navActive = 'sponsors';
include __DIR__ . '/../../../partials/header-sponsor-liaison.php';
?>
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/sponsors">Sponsors</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span aria-current="page"><?= e($record['name']) ?></span>
</nav>
<header class="page-header"><h1 class="page-header__title"><?= e($record['name']) ?></h1></header>
<section class="panel">
    <h2 class="panel__title">Sponsor details</h2>
    <dl class="facts">
        <div class="fact"><dt class="fact__label">Company</dt><dd class="fact__value"><?= e($record['name']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Contact</dt><dd class="fact__value"><?= e($record['email']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Total contribution</dt><dd class="fact__value"><?= e($record['points']) ?></dd></div>
    </dl>
    <p class="demo-note">Sample sponsor record. Editing is not available yet.</p>
</section>
<div class="actions">
    <a class="btn btn--primary" href="<?= base_url() ?>/sponsor-liaison/purchases?sponsor=<?= e(rawurlencode($record['name'])) ?>">View contributions</a>
    <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/sponsors">Back to sponsors</a>
</div>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
