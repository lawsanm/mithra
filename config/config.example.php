<?php

declare(strict_types=1);

/**
 * Copy this file to config/config.php and adjust for your machine.
 * config/config.php is git-ignored (Rules/CONVENTIONS.md §12) — never commit it.
 *
 * Code reads these values through Config::get(), never by requiring this file.
 */

return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'mithra',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
    'app' => [
        // 'local' shows password-reset links on screen when mail is off.
        // Anything else never does. Set 'production' on a real server.
        'env' => 'local',
        // Absolute base URL used in reset emails, e.g. https://mithra.example.lk
        // Required outside 'local' — the request's Host header is never trusted.
        'url' => '',
    ],
    'mail' => [
        // XAMPP has no mail server; turn on where PHP mail() can deliver.
        'enabled' => false,
        'from'    => 'no-reply@mithra.lk',
    ],
];
