<?php

declare(strict_types=1);

/**
 * aid_grants — the Aid Grant status screen.
 */
final class AidGrant extends BaseModel
{
    protected string $table = 'aid_grants';
    protected string $columns = 'id, member_id, requested_amount, approved_amount, purpose, status, created_at';

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

    /**
     * When the member last closed a grant, for the cooling-period notice.
     */
    public function lastClosedAt(int $memberId): ?string
    {
        $value = $this->selectValue(
            "SELECT MAX(created_at) FROM aid_grants
              WHERE member_id = :member AND status IN ('closed','expired')",
            ['member' => $memberId]
        );

        return $value === false || $value === null ? null : (string) $value;
    }

    public static function stageFor(string $status): int
    {
        return self::STAGE_OF_STATUS[$status] ?? 1;
    }
}
