<?php

declare(strict_types=1);

/** Cash reimbursement records: these never create or move points. */
final class ModeratorExpenseService
{
    public function __construct(private PDO $pdo, private Moderator $moderators, private ModeratorExpenseClaim $claims, private ModeratorConductHistory $history)
    {
    }

    public function save(int $assignmentId, int $adminId, array $input): void
    {
        Database::transaction($this->pdo, function () use ($assignmentId, $adminId, $input): void {
            $appointment = $this->moderators->lifecycle($assignmentId, true);
            if ($appointment === null) {
                throw new RecordNotFoundException('Appointment not found.');
            }
            $userId = (int) $appointment['user_id'];
            $id = (int) ($input['claim_id'] ?? 0);
            $claim = $id > 0 ? $this->claims->lockClaim($id, $userId) : null;
            if ($id > 0 && ($claim === null || $claim['paid_at'] !== null)) {
                throw ValidationException::field('claim_id', 'Choose an unpaid claim belonging to this moderator. Paid records cannot be changed.');
            }
            if ($input['action'] === 'pay' || $input['action'] === 'delete') {
                if ($claim === null) {
                    throw ValidationException::field('claim_id', 'Choose an existing unpaid claim.');
                }
                if ($input['action'] === 'pay') {
                    if ((int) $claim['approved_amount'] < 1 || (int) $claim['approved_amount'] > (int) $claim['amount']) {
                        throw ValidationException::field('claim_id', 'Record a valid approved amount before recording payment.');
                    }
                    $this->claims->markPaid($id, $adminId);
                } else {
                    $this->claims->deleteUnpaid($id);
                }
            } else {
                if ((int) $input['approved_amount'] > (int) $input['amount']) {
                    throw ValidationException::field('approved_amount', 'The approved amount cannot exceed the claimed amount.');
                }
                $this->claims->saveClaim($id > 0 ? $id : null, $userId, $adminId, $input);
            }
            $this->history->record($userId, $adminId, 'expense_' . $input['action'], json_encode(['claim_id' => $id, 'previous_record' => $claim, 'input' => $input], JSON_THROW_ON_ERROR));
        });
    }
}
