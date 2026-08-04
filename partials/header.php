<?php

declare(strict_types=1);

/**
 * Opening page chrome. A view sets $pageTitle and $navActive before including
 * this, then includes partials/footer.php at the end.
 *
 * @var string $pageTitle
 * @var string $navActive
 */

$pageTitle = $pageTitle ?? 'Mithra';
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
<?php include __DIR__ . '/nav.php'; ?>
<main class="page" id="main">
