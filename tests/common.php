<?php

declare(strict_types=1);

/**
 * What every PHP test file starts with: the app's autoloader and helpers, and
 * the one assertion they all use. A failed check throws, so the file stops
 * with a non-zero exit code and the message.
 */

require_once __DIR__ . '/../app/autoload.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
