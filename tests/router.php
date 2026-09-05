<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/autoload.php';

$routes = require __DIR__ . '/../app/routes.php';
$router = new Router($routes);
$checks = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Every listed URL resolves to the intended target and to an existing page/action.
foreach ($routes as $method => $table) {
    foreach ($table as $pattern => $target) {
        $match = $router->match(str_replace('{id}', '42', $pattern), $table);
        check($match !== null && $match[0] === $target, $method . ' ' . $pattern);
        if (is_string($target)) {
            check(is_file(__DIR__ . '/../views/' . $target . '.php'), 'Missing view: ' . $target);
        } else {
            check(method_exists($target[0], $target[1]), 'Missing controller action: ' . implode('::', $target));
        }
        $checks++;
    }
}

$table = ['/items/{id}' => 'show', '/items/create' => 'create'];
check($router->match('/items/create', $table) === ['create', []], 'Exact route must win.');
check($router->match('/items/42/', $table) === ['show', ['id' => '42']], 'Trailing slash must work.');
foreach (['/items/nope', '/items/0', '/items/-1', '/items/1.5', '/items/9999999999999999999999999', '/items/42/extra'] as $path) {
    check($router->match($path, $table) === null, 'Invalid item ID accepted: ' . $path);
}

// These requests must be rejected before opening a database connection.
session_start();
foreach (['', 'wrong-token', ['malformed']] as $token) {
    $_POST['csrf_token'] = $token;
    ob_start();
    $router->dispatch('POST', '/items');
    ob_end_clean();
    check(http_response_code() === 403, 'Invalid CSRF token accepted.');
}
foreach ([['POST', '/wallet', 405], ['GET', '/items/42/archive', 405], ['GET', '/missing', 404]] as [$method, $path, $status]) {
    ob_start();
    $router->dispatch($method, $path);
    ob_end_clean();
    check(http_response_code() === $status, 'Wrong status: ' . $method . ' ' . $path);
}

echo 'Passed: ' . $checks . " registered routes, URL matching, method handling and CSRF rejection.\n";
