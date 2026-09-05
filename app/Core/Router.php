<?php

declare(strict_types=1);

/** Matches a URL, checks submitted forms, then calls the controller. */
final class Router
{
    public function __construct(private array $routes)
    {
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
        if ($method !== 'GET') {
            $token = $_POST['csrf_token'] ?? '';
            if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
                $this->notice(403, 'That form has expired', 'Reload the page and try again. Nothing was changed.');
                return;
            }
        }

        $pdo = Database::connection();
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
