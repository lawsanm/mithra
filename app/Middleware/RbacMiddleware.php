<?php

declare(strict_types=1);

/**
 * The role check every signed-in request passes through (Plan §21.1,
 * Rules/CONVENTIONS.md §7.4).
 *
 * The rules are declared once, beside the routes, in the ROLES section of
 * app/routes.php: path prefix => roles allowed there. The longest prefix that
 * matches the path decides, so '/admin' governs '/admin/divisions/4' and
 * '/sponsor-liaison' is never mistaken for '/sponsor'.
 *
 * Fail closed (§8): a path no prefix covers, or a session without a role, is
 * refused. The sign-in screens are the only exception, because a visitor
 * reaching them has no role yet.
 *
 * Like AuthMiddleware it reads no superglobals, so it can be tested with plain
 * strings.
 */
final class RbacMiddleware
{
    /** A rule listing this matches any signed-in account. */
    private const ANY_ROLE = '*';

    /**
     * @param array<string, list<string>> $rules path prefix => allowed roles
     */
    public function __construct(private array $rules)
    {
    }

    /**
     * @param string|null $role the session's role code, or null when signed out
     *
     * @return bool true when this role may reach this path
     */
    public function handle(string $path, ?string $role): bool
    {
        $path = '/' . trim($path, '/');

        if (AuthMiddleware::isPublic($path)) {
            return true;
        }

        $allowed = $this->rolesFor($path);

        if ($allowed === null || $role === null || $role === '') {
            return false;
        }

        return in_array(self::ANY_ROLE, $allowed, true) || in_array($role, $allowed, true);
    }

    /**
     * @return list<string>|null the roles of the longest matching prefix
     */
    public function rolesFor(string $path): ?array
    {
        $path   = '/' . trim($path, '/');
        $best   = null;
        $length = -1;

        foreach ($this->rules as $prefix => $roles) {
            $covers = $prefix === '/'
                || $path === $prefix
                || str_starts_with($path, $prefix . '/');

            if ($covers && strlen($prefix) > $length) {
                $best   = $roles;
                $length = strlen($prefix);
            }
        }

        return $best;
    }
}
