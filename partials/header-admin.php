<?php

declare(strict_types=1);

/**
 * Opening page chrome for admin screens. A view sets $pageTitle and $navActive
 * before including this, then includes partials/footer.php at the end.
 *
 * @var string $pageTitle
 * @var string $navActive
 */

$pageTitle = $pageTitle ?? 'Mithra Admin';
$navActive = $navActive ?? '';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · Mithra</title>
    <link rel="stylesheet" href="/css/main.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<?php include __DIR__ . '/icon-sprite.php'; ?>
<?php include __DIR__ . '/nav-admin.php'; ?>
<main class="page" id="main">

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="notice notice--success notice--full"><?= e($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="notice notice--error notice--full"><?= e($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>
