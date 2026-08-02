<?php

declare(strict_types=1);

/**
 * The single PDO connection.
 *
 * Services and models receive a PDO through their constructor
 * (Rules/CONVENTIONS.md §6, DIP) — they never call Database::connection()
 * themselves. Only the bootstrap does, so tests can inject their own handle.
 */
final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                (string) Config::get('db.host', '127.0.0.1'),
                (int) Config::get('db.port', 3306),
                (string) Config::get('db.database', 'mithra'),
                (string) Config::get('db.charset', 'utf8mb4')
            );

            self::$connection = new PDO(
                $dsn,
                (string) Config::get('db.username', 'root'),
                (string) Config::get('db.password', ''),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Real prepared statements, so bound parameters are never
                    // interpolated into the SQL string client-side.
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }

        return self::$connection;
    }

    /**
     * Replace the handle — used by tests to point at a throwaway database.
     */
    public static function swap(?PDO $connection): void
    {
        self::$connection = $connection;
    }
}
