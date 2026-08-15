<?php

declare(strict_types=1);

/**
 * TEMPORARY view preview harness.
 *
 * The real front controller (public/index.php → Router → middleware →
 * Controller) is not built yet, but the views are. This renders any file under
 * /views so the UI can be reviewed in a browser:
 *
 *     php -S localhost:8000 -t public
 *     http://localhost:8000/preview.php
 *
 * DELETE THIS FILE once index.php dispatches real routes. It is a dev tool
 * only: it never writes, and it refuses any path outside /views.
 */

// The real bootstrap starts the session before any output; do the same here so
// csrf_field() can mint a token without warning about sent headers.
session_start();

require __DIR__ . '/preview-data.php';

$viewRoot = realpath(__DIR__ . '/../views');

/**
 * Every view under /views, as "feature/action" keys.
 *
 * @return list<string>
 */
function preview_views(string $viewRoot): array
{
    $found = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewRoot));

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relative = substr($file->getPathname(), strlen($viewRoot) + 1);
            $found[] = str_replace(['\\', '.php'], ['/', ''], $relative);
        }
    }

    sort($found);

    return $found;
}

$views = preview_views($viewRoot);
$requested = isset($_GET['page']) ? (string) $_GET['page'] : '';

if ($requested !== '' && in_array($requested, $views, true)) {
    // Stand in for the controller: fetch the row data, then render the view.
    extract(preview_data($requested), EXTR_SKIP);

    require $viewRoot . '/' . $requested . '.php';
    return;
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View preview · Mithra</title>
    <link rel="stylesheet" href="<?= base_url() ?>/css/main.css">
</head>
<body>
<main class="page">
    <header class="page-intro">
        <h1 class="page-intro__title">View preview</h1>
        <p class="page-intro__meta">
            <?= count($views) ?> views under /views. Temporary harness — delete once the front controller dispatches routes.
        </p>
    </header>

    <section class="section">
        <ul>
            <?php foreach ($views as $view): ?>
                <li class="list-row">
                    <div class="list-row__body">
                        <span class="list-row__title"><?= e($view) ?></span>
                        <span class="list-row__meta">views/<?= e($view) ?>.php</span>
                    </div>
                    <a class="btn btn--ghost" href="?page=<?= e(rawurlencode($view)) ?>">Open</a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</main>
</body>
</html>
