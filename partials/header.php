<?php

declare(strict_types=1);

/**
 * Opening page chrome for every screen. A view sets $pageTitle, $navActive and,
 * when the page belongs to one role's area, $chrome before including this,
 * then includes partials/footer.php at the end.
 *
 * @var string $pageTitle
 * @var string $navActive key of the current page's navigation link
 * @var string $chrome    member (default), moderator, admin, sponsor-liaison, sponsor or public
 * @var string $pageClass layout modifier for <main>, e.g. page--auth for the centred sign-in card
 */

$pageTitle = $pageTitle ?? 'Mithra';
$navActive = $navActive ?? '';
$chrome    = $chrome ?? 'member';
$pageClass = $pageClass ?? '';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · Mithra</title>
    <link rel="stylesheet" href="<?= e(asset_url('css/main.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<?php include __DIR__ . '/icon-sprite.php'; ?>
<?php include __DIR__ . '/nav.php'; ?>
<main class="page<?= $pageClass !== '' ? ' ' . e($pageClass) : '' ?>" id="main">
