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

    // Until Identity ships a login, the app renders as this member.
    'demo_member_id' => 4,
];
