<?php

declare(strict_types=1);

// All application requests start here. Only public/ is exposed to the browser.
require_once __DIR__ . '/../app/autoload.php';

if (!defined('APP_BASE')) {
    define('APP_BASE', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'));
}

session_start();
date_default_timezone_set('Asia/Colombo');

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
