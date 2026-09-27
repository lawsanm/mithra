<?php

declare(strict_types=1);

/**
 * Edit a sponsor's profile.
 *
 * @var array $sponsor           id, name, active
 * @var array $draft             current field values
 * @var array $errors            per-field messages
 * @var array $agreementStatuses value => label
 */

$draft             = $draft ?? [];
$errors            = $errors ?? [];
$agreementStatuses = $agreementStatuses ?? [];

$pageTitle = 'Edit ' . $sponsor['name'];
$navActive = 'sponsors';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../partials/header.php';

$profileUrl = base_url() . '/sponsor-liaison/sponsors/' . $sponsor['id'];

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/sponsors">Sponsors</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <a class="breadcrumb__link" href="<?= e($profileUrl) ?>"><?= e($sponsor['name']) ?></a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">Edit</span>
</nav>

<h1 class="detail__title">Edit sponsor</h1>

<?php if ($errors !== []): ?>
    <p class="notice notice--error" role="alert">Please correct the highlighted fields.</p>
<?php endif; ?>

<form class="form-card" method="post" action="<?= e($profileUrl) ?>" novalidate>
    <?= csrf_field() ?>

    <?php include __DIR__ . '/../../../partials/sponsor-profile-fields.php'; ?>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= e($profileUrl) ?>">Cancel</a>
        <button class="btn btn--primary" type="submit">Save changes</button>
    </div>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
