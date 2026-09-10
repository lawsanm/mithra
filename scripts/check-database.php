<?php

declare(strict_types=1);

// Readiness probe used by the local launcher; does not create or change data.
require_once __DIR__ . '/../app/autoload.php';

try {
    Database::serverConnection();
} catch (PDOException $exception) {
    exit(1);
}
