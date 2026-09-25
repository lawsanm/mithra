<?php

declare(strict_types=1);

/**
 * The signed-in check every request passes through (Rules/CONVENTIONS.md §7.4).
 *
 * Mithra's screens show one member's wallet, bookings and neighbours, so there
 * is nothing sensible to render for a visitor nobody has identified: everything
 * outside the sign-in screens needs a session, and a request without one is
 * sent to /login rather than served.
 *
 * Fail closed (§8): the allowed list below is exhaustive, so a route added
 * tomorrow is private until somebody deliberately names it here.
 *
 * This decides the rule only. The Router performs the redirect, and the class
 * reads no superglobals so it can be tested with a plain id.
 */
final class AuthMiddleware
{
    /**
     * The only paths a signed-out visitor may reach.
     *
     * / is the public home page: what Mithra is, how it works and the common
     * questions, for a visitor deciding whether to register.
     *
     * /logout is here because ending a session you no longer have should be a
     * no-op, never a redirect loop. The reset pages are here because the
     * member who needs them cannot sign in.
     */
    private const PUBLIC_PATHS = [
        '/',
        '/login',
        '/register',
        '/logout',
        '/forgot-password',
        '/reset-password',
        '/reset-password/code',
    ];

    /**
     * @param int|null $userId the signed-in member, or null for a visitor
     *
     * @return string|null the path to send them to instead, or null to let the
     *                     request through
     */
    public function handle(string $path, ?int $userId): ?string
    {
        if ($userId !== null && $userId > 0) {
            return null;
        }

        return self::isPublic($path) ? null : '/login';
    }

    public static function isPublic(string $path): bool
    {
        return in_array('/' . trim($path, '/'), self::PUBLIC_PATHS, true);
    }
}
