<?php

declare(strict_types=1);

/**
 * Read-only access to config/config.php.
 *
 * Nothing else in the codebase requires the config file directly
 * (Rules/CONVENTIONS.md §3) — everything goes through Config::get().
 */
final class Config
{
    /** @var array<string, mixed>|null */
    private static ?array $values = null;

    /**
     * Fetch a value by dot path, e.g. Config::get('db.host').
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all();

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (self::$values === null) {
            $dir  = dirname(__DIR__, 2) . '/config';
            $file = $dir . '/config.php';

            // A fresh clone has no config.php (git-ignored, §12). The example
            // file carries only stock XAMPP defaults — no secrets — so fall
            // back to it rather than fail before any local setup.
            if (!is_file($file)) {
                $file = $dir . '/config.example.php';
            }

            if (!is_file($file)) {
                throw new RuntimeException(
                    'config/config.php is missing. Copy config/config.example.php to config/config.php.'
                );
            }

            self::$values = require $file;
        }

        return self::$values;
    }
}
