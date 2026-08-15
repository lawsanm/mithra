<?php

declare(strict_types=1);

/**
 * TEMPORARY dev router for PHP's built-in server.
 *
 *     php -S localhost:8123 -t public public/router.php
 *
 * The built-in server has no .htaccess, so this file does what Apache's
 * rewrite rules do: serve real files as they are, send everything else to the
 * front controller. There the app owns the domain root, so APP_BASE is ''.
 *
 * DELETE THIS FILE, preview.php and preview-data.php once public/index.php
 * dispatches real routes.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Let the built-in server serve real files (CSS, JS, images) untouched.
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

define('APP_BASE', '');

require __DIR__ . '/index.php';

return true;
