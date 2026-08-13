<?php

declare(strict_types=1);

/**
 * TEMPORARY dev router for PHP's built-in server.
 *
 *     php -S localhost:8123 -t public public/router.php
 *
 * Resolves the real routes the views link to (/items/browse, /bookings/3, …)
 * onto view files, so the prototype can be clicked through end to end. It is a
 * stand-in for the front controller + Router + middleware chain, which is
 * frozen-core work owned by whoever builds it.
 *
 * DELETE THIS FILE, preview.php and preview-data.php once public/index.php
 * dispatches real routes.
 */

// The real bootstrap opens the session before any output; do the same so
// csrf_field() can mint a token without warning about sent headers.
session_start();

require_once __DIR__ . '/preview-data.php';

$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Let the built-in server serve real files (CSS, JS, images) untouched.
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

/**
 * Route table: pattern => view. {id} matches one path segment.
 *
 * @var array<string, string> $routes
 */
$routes = [
    '/'                        => 'dashboard/index',
    '/dashboard'               => 'dashboard/index',
    '/items'                   => 'items/index',
    '/items/browse'            => 'items/browse',
    '/items/create'            => 'items/create',
    '/items/{id}'              => 'items/show',
    '/items/{id}/edit'         => 'items/create',
    '/bookings'                => 'bookings/index',
    '/bookings/{id}'           => 'bookings/show',
    '/wallet'                  => 'wallet/index',
    '/gifts'                   => 'gifts/index',
    '/gifts/new'               => 'gifts/index',
    '/notifications'           => 'notifications/index',
    '/help'                    => 'help/index',
    '/settings'                => 'settings/index',
    '/settings/close-account'  => 'settings/index',
    '/transparency'            => 'transparency/index',
    '/ratings'                 => 'ratings/index',
    '/trust'                   => 'trust/index',
    '/profile'                 => 'profile/edit',
    '/members/{id}'            => 'profile/show',
    '/aid-grants'              => 'aid-grants/show',
    '/aid-grants/create'       => 'aid-grants/create',
    '/aid-grants/{id}'         => 'aid-grants/show',
    '/donations/{id}'          => 'donations/index',
    '/donations/{id}/handover' => 'donations/handover',
    '/community/temporary'     => 'community/create',

    // ── Admin ──
    '/admin'                          => 'admin/dashboard/index',
    '/admin/dashboard'                => 'admin/dashboard/index',
    '/admin/divisions'                => 'admin/divisions/index',
    '/admin/divisions/{id}'           => 'admin/divisions/show',
    '/admin/divisions/{id}/approvals' => 'admin/divisions/approvals',
    '/admin/moderators'               => 'admin/moderators/index',
    '/admin/moderators/performance'   => 'admin/moderators/performance',
    '/admin/moderators/appoint/{id}'  => 'admin/moderators/appoint',
    '/admin/moderators/objections/{id}' => 'admin/moderators/objections',
    '/admin/moderators/{id}'          => 'admin/moderators/show',
    '/admin/disputes'                 => 'admin/disputes/index',
    '/admin/disputes/{id}'            => 'admin/disputes/show',
    '/admin/disaster'                 => 'admin/disaster/index',
    '/admin/categories'               => 'admin/categories/index',
    '/admin/pools'                    => 'admin/pools/index',
    '/admin/pools/writeoffs'          => 'admin/pools/writeoffs',
    '/admin/pools/sponsor-ledger'     => 'admin/pools/sponsor-ledger',
    '/admin/pools/policies'           => 'admin/pools/policies',
    '/admin/ledger'                   => 'admin/ledger/index',
    '/admin/users'                    => 'admin/users/index',
    '/admin/users/{id}'               => 'admin/users/show',
    '/admin/cron'                     => 'admin/cron/index',
    '/admin/notifications'            => 'admin/notifications/index',
    '/admin/settings'                 => 'admin/settings/profile',
    '/admin/settings/profile'         => 'admin/settings/profile',
    '/admin/settings/security'        => 'admin/settings/security',
    '/admin/settings/notifications'   => 'admin/settings/notifications',

    // ── Moderator ──
    '/moderator'                         => 'moderator/dashboard/index',
    '/moderator/dashboard'               => 'moderator/dashboard/index',
    '/moderator/verifications'           => 'moderator/verifications/index',
    '/moderator/verifications/{id}'      => 'moderator/verifications/show',
    '/moderator/listing-approvals'       => 'moderator/listing-approvals/index',
    '/moderator/listing-approvals/{id}'  => 'moderator/listing-approvals/show',
    '/moderator/cases'                   => 'moderator/cases/index',
    '/moderator/cases/{id}'              => 'moderator/cases/show',
    '/moderator/disasters'               => 'moderator/disasters/index',
    '/moderator/aid-vouching'            => 'moderator/aid-vouching/index',

    // ── Sponsor Liaison ──
    '/sponsor-liaison'                          => 'sponsor-liaison/dashboard/index',
    '/sponsor-liaison/dashboard'                => 'sponsor-liaison/dashboard/index',
    '/sponsor-liaison/sponsors'                 => 'sponsor-liaison/sponsors/index',
    '/sponsor-liaison/sponsors/onboarding'      => 'sponsor-liaison/sponsors/onboarding',
    '/sponsor-liaison/purchases'                => 'sponsor-liaison/purchases/index',
    '/sponsor-liaison/purchases/create'         => 'sponsor-liaison/purchases/create',
    '/sponsor-liaison/points-pool'              => 'sponsor-liaison/points-pool/index',
    '/sponsor-liaison/disasters'                => 'sponsor-liaison/disasters/index',
    '/sponsor-liaison/disasters/connection'     => 'sponsor-liaison/disasters/connection',
    '/sponsor-liaison/aid-grants'                => 'sponsor-liaison/aid-grants/index',
    '/sponsor-liaison/aid-grants/{id}'          => 'sponsor-liaison/aid-grants/show',
    '/sponsor-liaison/csr-reports'              => 'sponsor-liaison/csr-reports/index',
    '/sponsor-liaison/csr-reports/quarterly'    => 'sponsor-liaison/csr-reports/quarterly',
];

/**
 * Match a path against the table.
 *
 * @param  array<string, string> $routes
 * @return array{0:string, 1:array<string,string>}|null view and its parameters
 */
function router_match(string $path, array $routes): ?array
{
    $path = rtrim($path, '/') ?: '/';

    if (isset($routes[$path])) {
        return [$routes[$path], []];
    }

    foreach ($routes as $pattern => $view) {
        if (!str_contains($pattern, '{')) {
            continue;
        }

        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';

        if (preg_match($regex, $path, $matches) === 1) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return [$view, $params];
        }
    }

    return null;
}

$matched = router_match($path, $routes);

// POSTs have no service layer behind them yet — say so plainly rather than 404.
if ($method !== 'GET' && $method !== 'HEAD') {
    http_response_code(501);
    router_notice(
        'Not wired up yet',
        sprintf('%s %s reached the router, but no controller or service handles it yet.', $method, $path),
        'The screens and the read layer are done; writes belong to the module that owns this route.'
    );

    return true;
}

if ($matched === null) {
    http_response_code(404);
    router_notice(
        'No screen for this route',
        sprintf('%s is linked from the UI but has no view in the Member canvas.', $path),
        'Add a frame in Figma and a view, or change the link.'
    );

    return true;
}

[$view, $params] = $matched;

$viewFile = dirname(__DIR__) . '/views/' . $view . '.php';

if (!is_file($viewFile)) {
    http_response_code(404);
    router_notice('View missing', $view . '.php does not exist.', '');

    return true;
}

// Route parameters join the query string, so views read them the same way.
$_GET += $params;

extract(preview_data($view, $params), EXTR_SKIP);

require $viewFile;

return true;

/**
 * Minimal styled message page, reusing the real stylesheet.
 */
function router_notice(string $title, string $detail, string $hint): void
{
    $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . $e($title) . ' · Mithra</title>'
       . '<link rel="stylesheet" href="/css/main.css"></head><body><main class="page">'
       . '<div class="empty-state"><p class="empty-state__title">' . $e($title) . '</p>'
       . '<p class="empty-state__body">' . $e($detail) . '</p>'
       . ($hint === '' ? '' : '<p class="empty-state__body">' . $e($hint) . '</p>')
       . '<a class="btn btn--primary" href="/dashboard">Back to dashboard</a>'
       . '</div></main></body></html>';
}
