<?php

declare(strict_types=1);

final class ModeratorObjection extends BaseModel
{
    protected string $table = 'moderator_objections';
    protected string $columns = 'id, moderator_assignment_id, member_id, reason, status, created_at, updated_at';

    public function lockForAssignment(int $id, int $assignmentId): ?array
    {
        return $this->selectOne('SELECT id, member_id, status FROM moderator_objections WHERE id = :id AND moderator_assignment_id = :assignment FOR UPDATE', ['id' => $id, 'assignment' => $assignmentId]);
    }

    public function record(int $assignmentId, int $memberId, string $reason): int
    {
        return $this->insert('INSERT INTO moderator_objections (moderator_assignment_id, member_id, reason) VALUES (:assignment, :member, :reason)', ['assignment' => $assignmentId, 'member' => $memberId, 'reason' => $reason]);
    }

    public function decide(int $id, string $status): void
    {
        $this->execute("UPDATE moderator_objections SET status = :status WHERE id = :id AND status = 'pending'", ['id' => $id, 'status' => $status]);
    }

    public function unresolvedCount(int $assignmentId): int
    {
        return (int) $this->selectValue("SELECT COUNT(*) FROM moderator_objections WHERE moderator_assignment_id = :id AND status <> 'dismissed'", ['id' => $assignmentId]);
    }

    public function pageForAssignment(int $id, int $page): array
    {
        return $this->selectPage('SELECT o.id, o.reason, o.status, o.created_at, u.full_name FROM moderator_objections o JOIN users u ON u.id = o.member_id WHERE o.moderator_assignment_id = :id ORDER BY o.id DESC', ['id' => $id], $page, 20);
    }
}
