<?php

declare(strict_types=1);

/**
 * Hourly: an accepted booking whose handover the two sides have not both
 * accepted within 48 hours of the start date is auto-cancelled, the borrower
 * gets everything back, and both are told (Plan §10.1).
 *
 *     C:\xampp\php\php.exe scripts\auto_cancel_handovers.php
 *
 * Safe to run twice: only bookings still 'awaiting_handover' are touched.
 */

require_once __DIR__ . '/../app/autoload.php';

$pdo = Database::connection();

exit((new CronJob(new CronRun($pdo)))->run(
    'auto_cancel_handovers',
    static fn (): string => BookingController::handovers($pdo)->autoCancelStale()
));
