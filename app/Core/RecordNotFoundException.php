<?php

declare(strict_types=1);

/**
 * No row matched the id in the URL (Rules/CONVENTIONS.md §8).
 *
 * Controllers render a 404. Services throw this rather than returning null so
 * a missing row can never be mistaken for an empty result set.
 */
final class RecordNotFoundException extends RuntimeException
{
}
