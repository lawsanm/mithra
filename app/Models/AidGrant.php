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
     * Who may see aid evidence: the member who sent it and their division's
     * moderator (the Sponsor Liaison and the Admin are checked by role).
     * Null when no grant carries the path.
     *
     * @return list<int>|null
     */
    public function evidenceViewers(string $path): ?array
    {
        $row = $this->selectOne(
            'SELECT g.member_id, d.moderator_id
               FROM aid_grants g
               JOIN gn_divisions d ON d.id = g.gn_division_id
              WHERE g.evidence_path = :path
                 OR JSON_CONTAINS(COALESCE(g.evidence_photos, \'[]\'), JSON_QUOTE(:photo))
              LIMIT 1',
            ['path' => $path, 'photo' => $path]
        );

        return $row === null ? null : self::ids($row);
    }

    /** Statuses in which a grant is still alive — at most one per member (§12.1). */
    public const LIVE_STATES = ['requested', 'vouched', 'info_requested', 'approved', 'disbursed', 'partially_returned'];

    /** How many of this member's grants are still alive. */
    public function countLiveFor(int $memberId): int
    {
        $params = ['member' => $memberId];

        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM aid_grants WHERE member_id = :member AND status IN ' . self::inList('state', self::LIVE_STATES, $params),
            $params
        );
    }

    /**
     * When the member last received a grant (approved at any amount), or null.
     */
    public function lastGrantedAt(int $memberId): ?string
    {
        $value = $this->selectValue(
            'SELECT MAX(created_at) FROM aid_grants WHERE member_id = :member AND approved_amount IS NOT NULL',
            ['member' => $memberId]
        );

        return $value === false || $value === null ? null : (string) $value;
    }

    /**
     * Points used against this year's cap: what was approved, plus what is
     * still being asked for.
     */
    public function usedThisYear(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COALESCE(SUM(CASE
                        WHEN approved_amount IS NOT NULL THEN approved_amount
                        WHEN status IN ('requested','vouched','info_requested') THEN requested_amount
                        ELSE 0 END), 0)
               FROM aid_grants
              WHERE member_id = :member AND YEAR(created_at) = YEAR(CURDATE())",
            ['member' => $memberId]
        );
    }

    /**
     * @param array{member_id:int, division_id:int, amount:int, purpose:string, details:string,
     *              evidence_photos:list<string>} $data
     */
    public function create(array $data): int
    {
        return $this->insert(
            "INSERT INTO aid_grants (member_id, gn_division_id, requested_amount, purpose, details, evidence_photos, status)
             VALUES (:member, :division, :amount, :purpose, :details, :photos, 'requested')",
            [
                'member'   => $data['member_id'],
                'division' => $data['division_id'],
                'amount'   => $data['amount'],
                'purpose'  => $data['purpose'],
                'details'  => $data['details'],
                'photos'   => json_encode($data['evidence_photos'], JSON_UNESCAPED_SLASHES),
            ]
        );
    }

    /**
     * The member's own grant, with everything the edit and reply forms need.
     *
     * @return array<string, mixed>|null
     */
    public function findOwned(int $id, int $memberId): ?array
    {
        return $this->selectOne(
            'SELECT id, member_id, gn_division_id, requested_amount, approved_amount, purpose, details,
                    evidence_photos, status, info_request, member_reply, decision_reason, vouched_at, created_at
               FROM aid_grants WHERE id = :id AND member_id = :member',
            ['id' => $id, 'member' => $memberId]
        );
    }

    /** Change a request the moderator has not vouched for yet. */
    public function updateRequest(int $id, int $memberId, int $amount, string $purpose, string $details): bool
    {
        return $this->execute(
            "UPDATE aid_grants SET requested_amount = :amount, purpose = :purpose, details = :details
              WHERE id = :id AND member_id = :member AND status = 'requested'",
            ['amount' => $amount, 'purpose' => $purpose, 'details' => $details, 'id' => $id, 'member' => $memberId]
        ) === 1;
    }

    /**
     * Answer the Liaison's question; the request goes back to where it was
     * asked from — the Liaison after a vouch, the moderator before one.
     */
    public function reply(int $id, int $memberId, string $reply): bool
    {
        return $this->execute(
            "UPDATE aid_grants
                SET member_reply = :reply, status = IF(vouched_at IS NULL, 'requested', 'vouched')
              WHERE id = :id AND member_id = :member AND status = 'info_requested'",
            ['reply' => $reply, 'id' => $id, 'member' => $memberId]
        ) === 1;
    }

    /** The member withdraws a request still being considered. */
    public function withdraw(int $id, int $memberId): bool
    {
        return $this->execute(
            "UPDATE aid_grants SET status = 'closed', decision_reason = 'Withdrawn by the member.'
              WHERE id = :id AND member_id = :member AND status IN ('requested','info_requested')",
            ['id' => $id, 'member' => $memberId]
        ) === 1;
    }
}
