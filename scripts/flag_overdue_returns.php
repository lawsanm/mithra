<?php

declare(strict_types=1);

/**
 * Hourly: items not returned (Plan §7.6). More than 72 hours past the return
 * date, the division's moderator and both members are told, once. Seven days
 * late, a total-loss claim opens on the moderator path.
 *
 *     C:\xampp\php\php.exe scripts\flag_overdue_returns.php
 *
 * Safe to run twice: flagged bookings carry overdue_flagged_at, and a claim
 * only opens on a booking still 'in_progress'.
 */

require_once __DIR__ . '/../app/autoload.php';

date_default_timezone_set('Asia/Colombo');

$pdo = Database::connection();

exit((new CronJob(new CronRun($pdo)))->run(
    'flag_overdue_returns',
    static fn (): string => BookingController::returns($pdo, new PhotoStore(dirname(__DIR__) . '/storage/uploads'))->flagOverdue()
));
