<?php

declare(strict_types=1);

/**
 * The logged-in member may not touch this record (Rules/CONVENTIONS.md §8).
 *
 * Thrown by services after an ownership check fails — an id in a URL is never
 * trusted. Controllers turn it into a 403, never into a redirect that hints
 * the record exists.
 */
final class AccessDeniedException extends RuntimeException
{
}
