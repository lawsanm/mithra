<?php

declare(strict_types=1);

/**
 * Onboard a sponsor. Figma "Sponsor — Onboarding" (377:155).
 *
 * @var array $draft             values entered so far
 * @var array $errors            per-field messages from the Validator
 * @var array $agreementStatuses value => label
 * @var array $accounts          sponsor login accounts that may be linked
 */

$draft             = $draft ?? [];
$errors            = $errors ?? [];
$agreementStatuses = $agreementStatuses ?? [];

$pageTitle = 'Onboard a sponsor';
$navActive = 'sponsors';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/sponsors">Sponsors</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">New sponsor</span>
</nav>

<h1 class="detail__title">Onboard a sponsor</h1>

<?php if ($errors !== []): ?>
    <p class="notice notice--error" role="alert">Please correct the highlighted fields.</p>
<?php endif; ?>

<form class="form-card" method="post" action="<?= base_url() ?>/sponsor-liaison/sponsors" novalidate>
    <?= csrf_field() ?>

    <?php include __DIR__ . '/../../../partials/sponsor-profile-fields.php'; ?>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/sponsors">Cancel</a>
        <button class="btn btn--primary" type="submit">Connect sponsor</button>
    </div>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
