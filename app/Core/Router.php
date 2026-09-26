<?php

declare(strict_types=1);

/**
 * Matches a URL, runs the middleware chain in the order the plan fixes
 * (Plan §21.3, Rules/CONVENTIONS.md §1): Auth → RBAC → CSRF, then the session
 * check, then calls the controller. The first three run before a database
 * connection is opened.
 */
final class Router
{
    private array $routes;

    /** @var array<string, list<string>> path prefix => roles, from routes.php */
    private array $roles;

    public function __construct(array $routes)
    {
        $this->roles = $routes['ROLES'] ?? [];
        unset($routes['ROLES']);
        $this->routes = $routes;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = base_url();
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');
        $method = $method === 'HEAD' ? 'GET' : $method;
        $match = $this->match($path, $this->routes[$method] ?? []);

        if ($match === null) {
            $allowed = [];
            foreach ($this->routes as $routeMethod => $routes) {
                if ($this->match($path, $routes) !== null) {
                    $allowed[] = $routeMethod;
                    if ($routeMethod === 'GET') {
                        $allowed[] = 'HEAD';
                    }
                }
            }
            if ($allowed !== []) {
                header('Allow: ' . implode(', ', $allowed));
                $this->notice(405, 'Action unavailable', 'This page is read-only in the interim demonstration.');
            } else {
                $this->notice(404, 'Page not found', 'This address is not available.');
            }
            return;
        }

        [$target, $params] = $match;

        // 1. Auth — a visitor nobody has identified goes to the sign-in screen.
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $signIn = (new AuthMiddleware())->handle($path, $userId);
        if ($signIn !== null) {
            header('Location: ' . base_url() . $signIn, true, 303);
            return;
        }

        // 2. RBAC — the role declared for this path in routes.php (§7.4).
        $role = isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;
        if (!(new RbacMiddleware($this->roles))->handle($path, $role)) {
            $this->notice(403, 'Not available to your account', 'This page belongs to a different role. Nothing was changed.');
            return;
        }

        // 3. CSRF — every state-changing request carries the session's token.
        if (!(new CsrfMiddleware())->handle($method, $_POST['csrf_token'] ?? null, csrf_token())) {
            $this->notice(403, 'That form has expired', 'Reload the page and try again. Nothing was changed.');
            return;
        }

        $pdo = Database::connection();

        // 4. Session — the account behind a signed-in session must still accept
        //    it. One primary-key lookup, after the cheap checks above.
        if ($userId !== null && $userId > 0 && !AuthMiddleware::isSignInPath($path)) {
            $ended = (new SessionMiddleware())->handle(
                (new User($pdo))->sessionState($userId),
                $role,
                isset($_SESSION['password_stamp']) ? (string) $_SESSION['password_stamp'] : null,
                isset($_SESSION['last_seen']) ? (int) $_SESSION['last_seen'] : null,
                time()
            );

            if ($ended !== null) {
                $_SESSION = ['flash' => ['type' => 'error', 'message' => $ended]];
                session_regenerate_id(true);
                header('Location: ' . base_url() . '/login', true, 303);
                return;
            }

            $_SESSION['last_seen'] = time();
        }

        if (is_string($target)) {
            $controller = str_starts_with($target, 'admin/')
                ? new AdminController($pdo)
                : new DemoController($pdo);
            $controller->show($target, $params);
            return;
        }

        [$class, $action] = $target;
        $controller = new $class($pdo);
        if (isset($params['id'])) {
            $controller->$action((int) $params['id']);
        } else {
            $controller->$action();
        }
    }

    /** Exact URLs win over numeric {id} routes, regardless of table order. */
    public function match(string $path, array $routes): ?array
    {
        $path = '/' . trim($path, '/');
        if (isset($routes[$path])) {
            return [$routes[$path], []];
        }

        foreach ($routes as $pattern => $target) {
            if (!str_contains($pattern, '{id}')) {
                continue;
            }
            $regex = '#^' . str_replace('\{id\}', '(?P<id>[1-9][0-9]*)', preg_quote($pattern, '#')) . '$#';
            if (preg_match($regex, $path, $matches) === 1
                && filter_var($matches['id'], FILTER_VALIDATE_INT) !== false) {
                return [$target, ['id' => $matches['id']]];
            }
        }
        return null;
    }

    private function notice(int $status, string $noticeTitle, string $noticeBody): void
    {
        http_response_code($status);
        require dirname(__DIR__, 2) . '/views/errors/notice.php';
    }
}
