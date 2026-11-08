<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

$now = new DateTimeImmutable('2026-11-04 12:00:00');
$trial = ['status' => 'trial', 'confirmed_at' => null, 'trial_ends_at' => '2026-11-04 12:00:00', 'objection_window_ends' => '2026-05-11 12:00:00'];
ModeratorLifecycleService::requireTrialComplete($trial, 0, $now);
$refused = static function (callable $action): void {
    try {
        $action();
        check(false, 'An invalid lifecycle transition was accepted.');
    } catch (ValidationException $expected) {
    }
};
$refused(fn () => ModeratorLifecycleService::requireTrialComplete(array_replace($trial, ['trial_ends_at' => '2026-11-05']), 0, $now));
$refused(fn () => ModeratorLifecycleService::requireTrialComplete($trial, 1, $now));
$refused(fn () => ModeratorLifecycleService::requireTrialComplete(array_replace($trial, ['status' => 'active']), 0, $now));
$refused(fn () => ModeratorLifecycleService::requireTrialComplete(array_replace($trial, ['trial_ends_at' => null]), 0, $now));

$pdo = Database::connection();
$wallets = new Wallet($pdo);
$pools = new PointPool($pdo);
$ledger = new LedgerService($pdo, new PointLedger($pdo), $pools, $wallets);
$moderators = new Moderator($pdo);
$history = new ModeratorConductHistory($pdo);
$lifecycle = new ModeratorLifecycleService($pdo, $moderators, new ModeratorObjection($pdo), $history, new GnDivision($pdo), new User($pdo), new ModeratorCandidate($pdo), $wallets, $ledger, new Notification($pdo));
$adminId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin' LIMIT 1")->fetchColumn();
check($adminId > 0, 'An Admin fixture is needed.');
$pdo->beginTransaction();
try {
    $tag = bin2hex(random_bytes(5));
    $phoneBase = random_int(100000000, 900000000);
    $insert = $pdo->prepare("INSERT INTO users (role_id, full_name, nic, phone, address, password_hash, status) SELECT id, 'Moderator lifecycle check', :nic, :phone, 'Test address', 'unused', 'active' FROM roles WHERE code = 'moderator'");
    $insert->execute(['nic' => 'T' . $tag, 'phone' => (string) $phoneBase]);
    $userId = (int) $pdo->lastInsertId();
    $insert = $pdo->prepare("INSERT INTO gn_divisions (name, district, moderator_id) VALUES (:name, 'Test district', :user)");
    $insert->execute(['name' => 'Lifecycle ' . $tag, 'user' => $userId]);
    $divisionId = (int) $pdo->lastInsertId();
    $wallets->openFor($userId);
    $pdo->exec("UPDATE point_pools SET balance = balance + 1000 WHERE pool_code = 'sponsor'");
    $before = $pools->totalBalance();
    $ledger->poolToMember('sponsor', $userId, 500, 'bond_hold');
    check($wallets->adjustBondLocked($userId, 500), 'Bond lock failed.');
    check($wallets->balance($userId) === 0, 'Bond was available to spend.');
    try {
        $ledger->memberToPool($userId, 'reserve', 1, 'bond_forfeit');
        check(false, 'Locked bond could be spent.');
    } catch (InsufficientPointsException $expected) {
    }
    $assignment = $moderators->createProvisional($userId, $divisionId, $adminId);
    $decision = ['action' => 'confirm', 'reason' => 'Performance reviewed', 'bond_decision' => '', 'objection_id' => ''];
    $refused(fn () => $lifecycle->decide($assignment, $adminId, $decision));
    $update = $pdo->prepare('UPDATE moderator_assignments SET trial_ends_at = NOW() - INTERVAL 1 DAY, objection_window_ends = NOW() - INTERVAL 5 MONTH WHERE id = :id');
    $update->execute(['id' => $assignment]);
    $lifecycle->decide($assignment, $adminId, $decision);
    check($moderators->lifecycle($assignment)['status'] === 'active', 'Trial was not confirmed.');
    $refused(fn () => $lifecycle->decide($assignment, $adminId, $decision));
    $sponsorBefore = $pools->balance('sponsor');
    $lifecycle->decide($assignment, $adminId, ['action' => 'remove', 'reason' => 'Voluntary resignation in good standing', 'bond_decision' => 'return']);
    check($pools->balance('sponsor') === $sponsorBefore + 500, 'Bond did not return to Sponsor Pool.');
    check($wallets->bondLocked($userId) === 0 && $wallets->balance($userId) === 0, 'Bond remained in the departed moderator wallet.');
    check((new GnDivision($pdo))->moderatedBy($userId) === null, 'Division was not released.');
    check($moderators->lifecycle($assignment)['status'] === 'resigned', 'Departure was not retained.');
    $refused(fn () => $lifecycle->decide($assignment, $adminId, ['action' => 'remove', 'reason' => 'Repeat', 'bond_decision' => 'return']));
    check($pools->totalBalance() === $before, 'Lifecycle movements created or destroyed points.');

    // Repeat with removal for cause and a real pending objection.
    (new User($pdo))->promoteActiveMemberToModerator($userId);
    (new GnDivision($pdo))->assignModerator($divisionId, $userId);
    $ledger->poolToMember('sponsor', $userId, 500, 'bond_hold');
    $wallets->adjustBondLocked($userId, 500);
    $second = $moderators->createProvisional($userId, $divisionId, $adminId);
    $objection = (new ModeratorObjection($pdo))->record($second, $adminId, 'Documented conflict');
    $refused(fn () => $lifecycle->decide($assignment, $adminId, ['action' => 'dismiss', 'reason' => 'Wrong appointment', 'objection_id' => $objection]));
    $reserveBefore = $pools->balance('reserve');
    $lifecycle->decide($second, $adminId, ['action' => 'uphold', 'reason' => 'Conflict established after review', 'objection_id' => $objection, 'bond_decision' => 'forfeit']);
    check($pools->balance('reserve') === $reserveBefore + 500, 'Forfeited bond did not reach Reserve.');
    check($moderators->lifecycle($second)['status'] === 'removed', 'Upheld objection did not end appointment.');
    check((new ModeratorObjection($pdo))->find($objection)['status'] === 'upheld', 'Decision was not retained.');
    check(count($history->forModerator($userId, 1)) >= 4, 'Permanent audit history is incomplete.');
    check($pools->totalBalance() === $before, 'Forfeiture changed total points.');

    // Exercise launch nomination, evaluation and appointment using fresh fixtures.
    $insert = $pdo->prepare("INSERT INTO gn_divisions (name, district) VALUES (:name, 'Test district')");
    $insert->execute(['name' => 'Launch ' . $tag]);
    $launchDivision = (int) $pdo->lastInsertId();
    $membership = $pdo->prepare("INSERT INTO user_divisions (user_id, gn_division_id, membership_type, status, verified_at) VALUES (:user, :division, 'home', 'active', NOW())");
    $membership->execute(['user' => $userId, 'division' => $launchDivision]);
    $memberInsert = $pdo->prepare("INSERT INTO users (role_id, full_name, nic, phone, address, password_hash, status) SELECT id, 'Launch test member', :nic, :phone, 'Test address', 'unused', 'active' FROM roles WHERE code = 'member'");
    for ($i = 0; $i < 9; $i++) {
        $memberInsert->execute(['nic' => 'T' . $tag . $i, 'phone' => (string) ($phoneBase + $i + 1)]);
        $nominator = (int) $pdo->lastInsertId();
        $membership->execute(['user' => $nominator, 'division' => $launchDivision]);
    }
    $candidateRecords = new ModeratorCandidate($pdo);
    $candidateService = new ModeratorCandidateService(new GnDivision($pdo), $candidateRecords);
    $nomination = $candidateService->nominate($launchDivision, $userId, $nominator);
    $candidateService->review($launchDivision, $nomination, ['endorsement' => 'GN confirmed', 'nic_verified' => true, 'residence_verified' => true, 'reference_1' => 'Household One', 'reference_2' => 'Household Two', 'declaration_signed' => true, 'interview_score' => 90, 'involvement_score' => 80, 'references_score' => 70, 'competency_score' => 100, 'declaration_reference' => 'Test signed declaration', 'capability_note' => 'Completed registration independently'], $adminId);
    $appointments = new ModeratorAppointmentService($pdo, new GnDivision($pdo), $candidateRecords, $candidateService, $moderators, new User($pdo), $wallets, $ledger, new Notification($pdo), $history);
    $refused(fn () => $appointments->appoint($launchDivision, $userId, $adminId, false));
    $pdo->exec("UPDATE point_pools SET balance = balance + 500 WHERE pool_code = 'sponsor'");
    $launchBefore = $pools->totalBalance();
    $launch = $appointments->appoint($launchDivision, $userId, $adminId, true);
    check($moderators->lifecycle($launch)['status'] === 'trial', 'Launch appointment did not start a trial.');
    check($wallets->bondLocked($userId) === 500 && $wallets->balance($userId) === 0, 'Launch bond was not protected.');
    $refused(fn () => $appointments->appoint($launchDivision, $userId, $adminId, true));
    check($pools->totalBalance() === $launchBefore, 'Appointment created points.');
    $lifecycle->decide($launch, $adminId, ['action' => 'record_objection', 'reason' => 'A specific concern received from a resident', 'member_id' => $nominator]);
    $received = (new ModeratorObjection($pdo))->pageForAssignment($launch, 1)[0];
    $refused(fn () => $lifecycle->decide($launch, $adminId, ['action' => 'dismiss', 'reason' => 'Wrong objection', 'objection_id' => $objection]));
    $lifecycle->decide($launch, $adminId, ['action' => 'dismiss', 'reason' => 'Evidence reviewed and concern resolved', 'objection_id' => $received['id']]);
    check((new ModeratorObjection($pdo))->find((int) $received['id'])['status'] === 'dismissed', 'Dismissal was not saved.');

    $expenses = new ModeratorExpenseClaim($pdo);
    $expenseService = new ModeratorExpenseService($pdo, $moderators, $expenses, $history);
    $expenseService->save($launch, $adminId, ['action' => 'save', 'claim_id' => '', 'purpose' => 'Travel for a division visit', 'amount' => '600', 'approved_amount' => '500', 'receipt_reference' => 'CHECK-' . $tag]);
    $claim = $expenses->forModerator($userId, 1)[0];
    $refused(fn () => $expenseService->save($launch, $adminId, ['action' => 'save', 'claim_id' => $claim['id'], 'purpose' => 'Travel', 'amount' => '600', 'approved_amount' => '700', 'receipt_reference' => 'CHECK']));
    $expenseService->save($launch, $adminId, ['action' => 'pay', 'claim_id' => $claim['id']]);
    check($expenses->find((int) $claim['id'])['paid_at'] !== null, 'Payment was not recorded.');
    $refused(fn () => $expenseService->save($launch, $adminId, ['action' => 'pay', 'claim_id' => $claim['id']]));
    $refused(fn () => $expenseService->save($launch, $adminId, ['action' => 'delete', 'claim_id' => $claim['id']]));
    check($pools->totalBalance() === $launchBefore, 'Cash expense recording changed points.');

    $staff = new LiaisonAssignmentService($pdo, new User($pdo), new Notification($pdo));
    $refused(fn () => $staff->assign($adminId, $adminId, 'assign'));
    $refused(fn () => $staff->assign($nominator, $adminId, 'assign'));
    $memberInsert->execute(['nic' => 'S' . $tag, 'phone' => (string) ($phoneBase + 20)]);
    $staffId = (int) $pdo->lastInsertId();
    $staff->assign($staffId, $adminId, 'assign');
    check((new User($pdo))->lockStaffAccount($staffId)['role_code'] === 'sponsor_liaison', 'Liaison role was not assigned.');
    $refused(fn () => $staff->assign($staffId, $adminId, 'assign'));
    // The original seed Liaison remains available when this temporary one leaves.
    if ((new User($pdo))->activeLiaisonCount() > 1) {
        $staff->assign($staffId, $adminId, 'remove');
        check((new User($pdo))->lockStaffAccount($staffId)['role_code'] === 'member', 'Liaison privileges were not removed.');
    }
} finally {
    $pdo->rollBack();
}
echo "Moderator lifecycle checks passed; database changes rolled back.\n";
