<?php

declare(strict_types=1);

final class ModeratorConductHistory extends BaseModel
{
    protected string $table = 'moderator_conduct_history';
    protected string $columns = 'id, moderator_id, incident_type, notes, recorded_by, recorded_at';

    public function record(int $userId, int $adminId, string $type, string $notes): void
    {
        $this->execute('INSERT INTO moderator_conduct_history (moderator_id, recorded_by, incident_type, notes) VALUES (:user, :admin, :type, :notes)', ['user' => $userId, 'admin' => $adminId, 'type' => $type, 'notes' => $notes]);
    }

    public function forModerator(int $userId, int $page): array
    {
        return $this->selectPage('SELECT h.incident_type, h.notes, h.recorded_at, u.full_name AS recorded_by FROM moderator_conduct_history h LEFT JOIN users u ON u.id = h.recorded_by WHERE h.moderator_id = :user ORDER BY h.id DESC', ['user' => $userId], $page, 20);
    }
}
