<?php

declare(strict_types=1);

$pageTitle = $record['receipt'];
$navActive = 'purchases';
include __DIR__ . '/../../../partials/header-sponsor-liaison.php';
?>
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/purchases">Contributions</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span aria-current="page"><?= e($record['receipt']) ?></span>
</nav>
<header class="page-header"><h1 class="page-header__title">Contribution <?= e($record['receipt']) ?></h1></header>
<section class="panel">
    <h2 class="panel__title"><?= e($record['sponsor']) ?></h2>
    <dl class="facts">
        <div class="fact"><dt class="fact__label">Recorded</dt><dd class="fact__value"><?= e($record['date']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Amount</dt><dd class="fact__value"><?= e($record['amount']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Receipt</dt><dd class="fact__value"><?= e($record['receipt']) ?></dd></div>
    </dl>
    <p class="panel__note"><?= e($record['allocation']) ?></p>
    <p class="demo-note">Sample contribution record. No payment is made from this page.</p>
</section>
<a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/purchases">Back to contributions</a>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
