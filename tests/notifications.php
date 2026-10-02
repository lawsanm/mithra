<?php

declare(strict_types=1);

/**
 * Notification checks (Plan 4.6).
 *
 *     C:\xampp\php\php.exe tests\notifications.php
 *
 * Without a database: every notification type a service sends belongs to a
 * filter pill, so none is lost under "All" or mis-worded (I3).
 *
 * With the local database (skipped when it is not reachable, as in CI's rule
 * step): a member can only read, mark or dismiss their own notifications.
 * Those writes run in a transaction that is rolled back.
 */

require_once __DIR__ . '/common.php';

$checks = 0;

$grouped = array_merge(...array_values(Notification::GROUPS));

foreach (glob(__DIR__ . '/../app/Services/*.php') ?: [] as $file) {
    // Literal types only; a type built at run time ('listing_' . $decision) is checked by hand.
    preg_match_all("/->push\\([^,]+,\\s*'([a-z_]+)',/", (string) file_get_contents($file), $matches);

    foreach ($matches[1] as $type) {
        check(in_array($type, $grouped, true), basename($file) . " sends '$type', which no filter pill covers.");
        $checks++;
    }
}

check(count($grouped) === count(array_unique($grouped)), 'A type belongs to one pill only.');
$checks++;

try {
    $pdo = Database::connection();
} catch (PDOException $exception) {
    echo 'Passed: ' . $checks . " notification checks — every sent type has a pill. (No database: ownership checks skipped.)\n";

    return;
}

$model = new Notification($pdo);
$pdo->beginTransaction();

try {
    $model->push(2, 'account_notice', ['title' => 'Test notice', 'detail' => '', 'icon' => 'info', 'href' => '/dashboard']);
    $id = (int) $pdo->lastInsertId();

    check($model->findForMember($id, 3) === null, 'Another member must not see it.');
    $model->markRead($id, 3);
    check($model->findForMember($id, 2)['unread'] === true, 'Another member must not mark it read.');
    check(!$model->deleteOwned($id, 3), 'Another member must not dismiss it.');
    $model->markRead($id, 2);
    check($model->findForMember($id, 2)['unread'] === false, 'The owner marks it read.');
    check($model->deleteOwned($id, 2), 'The owner dismisses it.');
    $checks += 5;
} finally {
    $pdo->rollBack();
}

echo 'Passed: ' . $checks . " notification checks — every sent type has a pill, only the owner reads or changes one.\n";
