<?php

declare(strict_types=1);

/**
 * Hand-written SPL autoloader (Rules/CONVENTIONS.md §2 — no Composer packages).
 *
 * Class name = file name, searched in the directories below in order.
 */

spl_autoload_register(static function (string $class): void {
    $directories = [
        __DIR__ . '/Core/',
        __DIR__ . '/Models/',
        __DIR__ . '/Controllers/',
    ];

    foreach ($directories as $directory) {
        $file = $directory . $class . '.php';

        if (is_file($file)) {
            require $file;

            return;
        }
    }
});

require_once __DIR__ . '/helpers.php';
