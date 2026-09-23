<?php

declare(strict_types=1);

/**
 * Change password — one page for every role, in that role's page chrome.
 *
 * @var string $chrome   the signed-in role's navigation (chrome_for())
 * @var array  $errors   per-field messages
 * @var string $returnTo the whitelisted page to return to after saving
 * @var array|null $flash
 */

$errors   = $errors ?? [];
$returnTo = $returnTo ?? '/account/password';

$pageTitle = 'Change password';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="page-header__title">Change password</h1>

<?php include __DIR__ . '/../../partials/flash.php'; ?>

<?php include __DIR__ . '/../../partials/change-password-form.php'; ?>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
