<?php

declare(strict_types=1);

/**
 * Nightly: recalculate every member's trust score (Plan §6.3). Events update
 * scores as they happen; this catches what moves on its own — tenure, and
 * penalties dropping out of their 12-month window.
 *
 *     C:\xampp\php\php.exe scripts\refresh_trust_scores.php
 *
 * Safe to run any number of times: it only ever writes the computed score.
 */

require_once __DIR__ . '/../app/autoload.php';

date_default_timezone_set('Asia/Colombo');

$pdo = Database::connection();

exit((new CronJob(new CronRun($pdo)))->run(
    'refresh_trust_scores',
    static fn (): string => TrustController::service($pdo)->refreshAll()
));
