<?php

declare(strict_types=1);

/**
 * Daily: temporary community reminders, pauses and expiries (Plan §6.5).
 *
 *     C:\xampp\php\php.exe scripts\expire_temporary_memberships.php
 *
 * 14 days before expiry the member is reminded; at expiry the membership
 * pauses and so do their listings there; 14 days later, with no extension, it
 * ends. Safe to run twice: each step only touches rows still waiting for it.
 */

require_once __DIR__ . '/../app/autoload.php';

date_default_timezone_set('Asia/Colombo');

$pdo = Database::connection();

exit((new CronJob(new CronRun($pdo)))->run(
    'expire_temporary_memberships',
    static fn (): string => CommunityController::service($pdo, new PhotoStore(dirname(__DIR__) . '/storage/uploads'))->runExpiry()
));
