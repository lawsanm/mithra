<?php

declare(strict_types=1);

/**
 * aid_grants — the Aid Grant status screen.
 */
final class AidGrant extends BaseModel
{
    protected string $table = 'aid_grants';
    protected string $columns = 'id, member_id, requested_amount, approved_amount, purpose, status, created_at';

    /** Joined records shared by member, moderator and liaison screens. */
    public function records(?int $memberId = null, ?int $divisionId = null): array
    {
        return $this->select(
            'SELECT g.id, g.member_id, g.gn_division_id, g.requested_amount, g.approved_amount,
                    g.purpose, g.status, g.created_at, g.moderator_vouch, g.vouched_at,
                    u.full_name AS member_name, u.trust_score, d.name AS division_name,
                    m.full_name AS moderator_name, l.full_name AS liaison_name
               FROM aid_grants g JOIN users u ON u.id = g.member_id
               JOIN gn_divisions d ON d.id = g.gn_division_id
          LEFT JOIN users m ON m.id = g.moderator_id
          LEFT JOIN users l ON l.id = g.liaison_id
              WHERE (:all_members = 1 OR g.member_id = :member)
                AND (:all_divisions = 1 OR g.gn_division_id = :division)
              ORDER BY g.created_at DESC, g.id DESC',
            ['all_members' => (int) ($memberId === null), 'member' => $memberId ?? 0,
             'all_divisions' => (int) ($divisionId === null), 'division' => $divisionId ?? 0]
        );
    }

    public static function stage(string $status): int
    {
        return self::STAGE_OF_STATUS[$status] ?? 1;
    }

    /** Workflow stages shown by the read-only progress meter. */
    private const STAGE_OF_STATUS = [
        'requested'          => 1,
        'info_requested'     => 1,
        'rejected_moderator' => 1,
        'vouched'            => 2,
        'rejected_liaison'   => 2,
        'approved'           => 3,
        'disbursed'          => 4,
        'partially_returned' => 4,
        'expired'            => 5,
        'closed'             => 5,
    ];

    /**
     * The member's current (non-closed) grant, if any.
     *
     * @return array<string, mixed>|null
     */
    public function activeForMember(int $memberId): ?array
    {
        return $this->selectOne(
            "SELECT g.id, g.requested_amount, g.approved_amount, g.purpose, g.status,
                    g.created_at, g.moderator_vouch, g.vouched_at,
                    d.name AS division_name,
                    m.full_name AS moderator_name
               FROM aid_grants g
               JOIN gn_divisions d ON d.id = g.gn_division_id
          LEFT JOIN users m        ON m.id = g.moderator_id
              WHERE g.member_id = :member AND g.status NOT IN ('closed','expired')
              ORDER BY g.created_at DESC
              LIMIT 1",
            ['member' => $memberId]
        );
    }
}
