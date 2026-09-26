<?php

declare(strict_types=1);

/**
 * The signed-in check every request passes through (Rules/CONVENTIONS.md §7.4).
 *
 * Mithra's screens show one member's wallet, bookings and neighbours, so there
 * is nothing sensible to render for a visitor nobody has identified: apart from
 * the sign-in screens and the few pages that explain Mithra to a newcomer,
 * everything needs a session, and a request without one is sent to /login
 * rather than served.
 *
 * Fail closed (§8): the allowed lists below are exhaustive, so a route added
 * tomorrow is private until somebody deliberately names it here.
 *
 * This decides the rule only. The Router performs the redirect, and the class
 * reads no superglobals so it can be tested with a plain id.
 */
final class AuthMiddleware
{
    /**
     * The sign-in screens.
     *
     * /logout is here because ending a session you no longer have should be a
     * no-op, never a redirect loop. The reset pages are here because the
     * member who needs them cannot sign in. The Router does not check a
     * session on these paths, so an ended session can still reach them.
     */
    private const SIGN_IN_PATHS = [
        '/login',
        '/register',
        '/register/pending',
        '/logout',
        '/forgot-password',
        '/reset-password',
        '/reset-password/code',
    ];

    /**
     * Pages that explain Mithra to someone deciding whether to join. They hold
     * nothing about any member, and a signed-in visitor still has their
     * session checked on them.
     */
    private const OPEN_PATHS = [
        '/',
        '/how-it-works',
        '/transparency',
        '/help',
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

    /** Whether a signed-out visitor may reach this path. */
    public static function isPublic(string $path): bool
    {
        return self::isSignInPath($path) || in_array(self::normalise($path), self::OPEN_PATHS, true);
    }

    public static function isSignInPath(string $path): bool
    {
        return in_array(self::normalise($path), self::SIGN_IN_PATHS, true);
    }

    private static function normalise(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
