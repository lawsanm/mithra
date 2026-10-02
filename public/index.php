<?php

declare(strict_types=1);

// All application requests start here. Only public/ is exposed to the browser.
require_once __DIR__ . '/../app/autoload.php';

if (!defined('APP_BASE')) {
    define('APP_BASE', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'));
}

// The session cookie carries the only proof of who is signing these requests
// (Rules/CONVENTIONS.md §7.7), so it is kept away from JavaScript, away from
// cross-site requests, and off plain HTTP wherever TLS is available.
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => ($_SERVER['HTTPS'] ?? '') !== '',
]);

session_start();

$router = new Router(require __DIR__ . '/../app/routes.php');

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $exception) {
    error_log((string) $exception);
    http_response_code(500);
    $noticeTitle = 'Something went wrong';
    $noticeBody = 'The page could not be loaded. Please try again shortly.';
    require __DIR__ . '/../views/errors/notice.php';
}
