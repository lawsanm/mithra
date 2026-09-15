<?php

declare(strict_types=1);

/**
 * The forged-form check (Plan §21.1, Rules/CONVENTIONS.md §7.3).
 *
 * Every state-changing request must carry the session's token, which
 * csrf_field() prints into every form. GET never changes state, so it is not
 * checked. The comparison is constant-time, and anything that is not a string
 * — a missing field, an array smuggled in as csrf_token[] — is refused.
 */
final class CsrfMiddleware
{
    /**
     * @param mixed  $submitted the token the form posted, as received
     * @param string $expected  the session's token
     *
     * @return bool true when the request may go on
     */
    public function handle(string $method, mixed $submitted, string $expected): bool
    {
        if ($method === 'GET') {
            return true;
        }

        return is_string($submitted) && $expected !== '' && hash_equals($expected, $submitted);
    }
}
