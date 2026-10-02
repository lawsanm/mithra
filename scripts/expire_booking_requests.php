<?php

declare(strict_types=1);

/**
 * Hourly: booking requests the lender did not answer within 24 hours are
 * auto-cancelled, and both members are told (Plan §8, Figma "Booking —
 * Auto-Cancelled"). No points had moved, so nothing is refunded.
 *
 *     C:\xampp\php\php.exe scripts\expire_booking_requests.php
 *
 * Safe to run twice: only bookings still 'requested' are touched.
 */

require_once __DIR__ . '/../app/autoload.php';

date_default_timezone_set('Asia/Colombo');

$pdo = Database::connection();

exit((new CronJob(new CronRun($pdo)))->run(
    'expire_booking_requests',
    static fn (): string => BookingController::service($pdo)->expireRequests()
));
