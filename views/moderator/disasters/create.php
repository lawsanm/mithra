<?php

declare(strict_types=1);

/**
 * Record relief handed out during the division's active disaster.
 *
 * @var array $draft       current field values
 * @var array $errors      per-field messages
 * @var array $reliefTypes value => label
 * @var array $sponsors    rows: id, company_name
 */

$draft  = $draft ?? [];
$errors = $errors ?? [];

$pageTitle = 'Record relief given';
$navActive = 'disasters';

$chrome = 'moderator';
include __DIR__ . '/../../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/moderator/disasters">Disaster relief</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Record relief</span>
</nav>

<h1 class="detail__title">Record relief given</h1>

<?php if ($errors !== []): ?>
    <p class="notice notice--error" role="alert">Please correct the highlighted fields.</p>
<?php endif; ?>

<form class="form-card" method="post" action="<?= base_url() ?>/moderator/disasters/relief" novalidate>
    <?= csrf_field() ?>

    <?php include __DIR__ . '/../../../partials/relief-record-fields.php'; ?>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/moderator/disasters">Cancel</a>
        <button class="btn btn--primary" type="submit">Save record</button>
    </div>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
