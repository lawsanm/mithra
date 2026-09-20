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

$roleRules = $routes['ROLES'];
unset($routes['ROLES']);
$rbac = new RbacMiddleware($roleRules);

// Every listed URL resolves to the intended target and to an existing page/action,
// and is covered by a role rule — an uncovered route would be refused for everyone.
foreach ($routes as $method => $table) {
    foreach ($table as $pattern => $target) {
        $match = $router->match(str_replace('{id}', '42', $pattern), $table);
        check($match !== null && $match[0] === $target, $method . ' ' . $pattern);
        if (is_string($target)) {
            check(is_file(__DIR__ . '/../views/' . $target . '.php'), 'Missing view: ' . $target);
        } else {
            check(method_exists($target[0], $target[1]), 'Missing controller action: ' . implode('::', $target));
        }
        check($rbac->rolesFor($pattern) !== null, 'No role rule covers ' . $pattern);
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
// CSRF runs after Auth and RBAC (Plan §21.3), so the forger here is a signed-in member.
session_start();
$_SESSION['user_id'] = 4;
$_SESSION['role'] = 'member';
foreach (['', 'wrong-token', ['malformed']] as $token) {
    $_POST['csrf_token'] = $token;
    ob_start();
    $router->dispatch('POST', '/items');
    ob_end_clean();
    check(http_response_code() === 403, 'Invalid CSRF token accepted.');
}
$csrf = new CsrfMiddleware();
check($csrf->handle('GET', null, 'abc'), 'GET is never token-checked.');
check($csrf->handle('POST', 'abc', 'abc'), 'The session token must pass.');
check(!$csrf->handle('POST', 'abc', ''), 'An empty session token must never match.');
$checks += 3;

// A signed-in account outside a path's role is refused before any controller
// or database connection (Plan §16.4, §21.1).
foreach ([
    ['member', 'GET', '/admin'],
    ['member', 'GET', '/admin/divisions'],
    ['member', 'POST', '/admin/divisions'],
    ['member', 'POST', '/admin/divisions/1/archive'],
    ['member', 'GET', '/moderator/verifications'],
    ['member', 'POST', '/moderator/verifications/3/approve'],
    ['member', 'GET', '/sponsor-liaison/aid-grants'],
    ['member', 'POST', '/sponsor-liaison/sponsors'],
    ['admin', 'POST', '/sponsor-liaison/sponsors/1'],
    ['sponsor', 'POST', '/sponsor-liaison/sponsors/1/deactivate'],
    ['moderator', 'GET', '/sponsor-liaison/sponsors/1/edit'],
    ['member', 'GET', '/sponsor/dashboard'],
    ['sponsor', 'GET', '/sponsor-liaison'],
    ['sponsor_liaison', 'GET', '/sponsor/dashboard'],
    ['moderator', 'GET', '/admin/users'],
    ['admin', 'GET', '/wallet'],
    ['admin', 'POST', '/items'],
] as [$role, $method, $path]) {
    $_SESSION['role'] = $role;
    $_POST['csrf_token'] = csrf_token();
    ob_start();
    $router->dispatch($method, $path);
    ob_end_clean();
    check(http_response_code() === 403, $role . ' reached ' . $method . ' ' . $path);
    $checks++;
}
$_SESSION['role'] = 'member';

foreach ([
    ['/admin/divisions/4', 'admin', true],
    ['/moderator/listing-approvals/7', 'moderator', true],
    ['/sponsor-liaison/purchases', 'sponsor_liaison', true],
    ['/sponsor/branding', 'sponsor', true],
    ['/items/42', 'member', true],
    ['/items/42', 'moderator', true],
    ['/transparency', 'sponsor', true],
    ['/help', 'admin', true],
    ['/login', null, true],
    ['/items', null, false],
    ['/items', '', false],
    ['/admin', 'superuser', false],
] as [$path, $role, $expected]) {
    check($rbac->handle($path, $role) === $expected, 'RBAC decision wrong for ' . var_export($role, true) . ' on ' . $path);
    $checks++;
}
check($rbac->rolesFor('/sponsor-liaison/x') === ['sponsor_liaison'], 'The longest prefix must win.');
check((new RbacMiddleware(['/admin' => ['admin']]))->rolesFor('/wallet') === null, 'An uncovered path has no rule.');
check(!(new RbacMiddleware(['/admin' => ['admin']]))->handle('/wallet', 'admin'), 'An uncovered path must be refused.');
$checks += 3;

foreach ([['POST', '/wallet', 405], ['GET', '/items/42/archive', 405], ['GET', '/missing', 404]] as [$method, $path, $status]) {
    ob_start();
    $router->dispatch($method, $path);
    ob_end_clean();
    check(http_response_code() === $status, 'Wrong status: ' . $method . ' ' . $path);
}

// A signed-out request is turned away before the router reaches a controller,
// so no page is rendered and no database connection is opened.
unset($_SESSION['user_id']);
foreach (['/', '/dashboard', '/items', '/admin', '/moderator/verifications'] as $path) {
    ob_start();
    $router->dispatch('GET', $path);
    $body = (string) ob_get_clean();
    check($body === '', 'Signed-out request rendered a page: ' . $path);
    $checks++;
}

// The sign-in screens are the exception, and every other path is not.
$gate = new AuthMiddleware();
foreach (['/login', '/register', '/logout', '/login/', '/register/'] as $path) {
    check($gate->handle($path, null) === null, 'Signed-out visitor must reach ' . $path);
    $checks++;
}
foreach (['/', '/dashboard', '/items/42', '/admin', '/wallet', '/loginx', '/register/step-2'] as $path) {
    check($gate->handle($path, null) === '/login', 'Path must require a session: ' . $path);
    $checks++;
}
foreach ([null, 0, -1] as $absent) {
    check($gate->handle('/dashboard', $absent) === '/login', 'A missing member id is not a session.');
    $checks++;
}
check($gate->handle('/dashboard', 4) === null, 'A signed-in member must pass.');
$checks++;

echo 'Passed: ' . $checks . " registered routes, URL matching, method handling, sign-in, role and CSRF rejection.\n";
