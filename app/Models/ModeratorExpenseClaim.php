<?php

declare(strict_types=1);

final class ModeratorExpenseClaim extends BaseModel
{
    protected string $table = 'moderator_expense_claims';
    protected string $columns = 'id, moderator_id, purpose, amount, receipt_reference, checked_by, approved_amount, paid_at, created_at';

    public function forModerator(int $userId, int $page): array
    {
        return $this->selectPage('SELECT id, purpose, amount, receipt_reference, approved_amount, paid_at, created_at FROM moderator_expense_claims WHERE moderator_id = :user ORDER BY id DESC', ['user' => $userId], $page, 20);
    }

    public function lockClaim(int $id, int $userId): ?array
    {
        return $this->selectOne('SELECT id, purpose, amount, receipt_reference, approved_amount, paid_at FROM moderator_expense_claims WHERE id = :id AND moderator_id = :user FOR UPDATE', ['id' => $id, 'user' => $userId]);
    }

    public function saveClaim(?int $id, int $userId, int $adminId, array $input): void
    {
        $params = ['purpose' => $input['purpose'], 'amount' => (int) $input['amount'], 'receipt' => $input['receipt_reference'], 'approved' => (int) $input['approved_amount'], 'admin' => $adminId];
        if ($id === null) {
            $this->execute('INSERT INTO moderator_expense_claims (moderator_id, purpose, amount, receipt_reference, approved_amount, checked_by) VALUES (:user, :purpose, :amount, :receipt, :approved, :admin)', $params + ['user' => $userId]);
        } else {
            $this->execute('UPDATE moderator_expense_claims SET purpose = :purpose, amount = :amount, receipt_reference = :receipt, approved_amount = :approved, checked_by = :admin WHERE id = :id AND paid_at IS NULL', $params + ['id' => $id]);
        }
    }

    public function markPaid(int $id, int $adminId): void
    {
        $this->execute('UPDATE moderator_expense_claims SET paid_at = NOW(), checked_by = :admin WHERE id = :id AND paid_at IS NULL', ['id' => $id, 'admin' => $adminId]);
    }

    public function deleteUnpaid(int $id): void
    {
        $this->execute('DELETE FROM moderator_expense_claims WHERE id = :id AND paid_at IS NULL', ['id' => $id]);
    }
}
