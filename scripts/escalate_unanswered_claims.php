<?php

declare(strict_types=1);

/**
 * Hourly: a simple-path damage claim the borrower has not accepted or
 * contested within 48 hours goes to the division's moderator (Plan §10.4).
 *
 *     C:\xampp\php\php.exe scripts\escalate_unanswered_claims.php
 *
 * Safe to run twice: only claims still 'awaiting_borrower' are touched.
 */

require_once __DIR__ . '/../app/autoload.php';

date_default_timezone_set('Asia/Colombo');

$pdo = Database::connection();

exit((new CronJob(new CronRun($pdo)))->run(
    'escalate_unanswered_claims',
    static fn (): string => DamageClaimController::service($pdo, new PhotoStore(dirname(__DIR__) . '/storage/uploads'))->escalateUnanswered()
));
