<?php

declare(strict_types=1);

/**
 * Hourly: end Disaster Mode events when their planned end date arrives.
 * Safe to run repeatedly; only due, open events are changed.
 *
 *     C:\xampp\php\php.exe scripts\expire_disaster_events.php
 */

require_once __DIR__ . '/../app/autoload.php';

$pdo = Database::connection();

$service = new DisasterModeService(
    $pdo,
    new GnDivision($pdo),
    new DisasterEvent($pdo),
    new User($pdo),
    new Notification($pdo)
);

exit((new CronJob(new CronRun($pdo)))->run(
    'expire_disaster_events',
    static fn (): string => $service->expirePlannedEvents()
));
