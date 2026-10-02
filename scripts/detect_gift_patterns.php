<?php

declare(strict_types=1);

/**
 * Daily: pairs of members who send gifts back and forth three times within
 * 30 days are reported to their division's moderator (Plan §19).
 *
 *     C:\xampp\php\php.exe scripts\detect_gift_patterns.php
 *
 * Safe to run twice: a moderator hears about a pair once per 30 days.
 */

require_once __DIR__ . '/../app/autoload.php';

date_default_timezone_set('Asia/Colombo');

$pdo = Database::connection();

exit((new CronJob(new CronRun($pdo)))->run(
    'detect_gift_patterns',
    static fn (): string => GiftController::service($pdo)->detectPatterns()
));
