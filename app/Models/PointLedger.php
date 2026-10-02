<?php

declare(strict_types=1);

final class PointLedger extends BaseModel
{
    protected string $table = 'point_ledger';
    protected string $columns = 'id, from_pool_code, from_user_id, to_pool_code, to_user_id, amount, reason, created_at';

    private const PER_PAGE = 25;

    public function sponsorPoolActivity(): array
    {
        return $this->select(
            "SELECT l.amount, l.reason, l.created_at, l.from_pool_code, l.to_pool_code,
                    fu.full_name AS from_name, tu.full_name AS to_name,
                    s.company_name, c.receipt_number
               FROM point_ledger l
          LEFT JOIN users fu ON fu.id = l.from_user_id
          LEFT JOIN users tu ON tu.id = l.to_user_id
          LEFT JOIN sponsor_contributions c ON c.id = l.contribution_id
          LEFT JOIN sponsors s ON s.id = c.sponsor_id
              WHERE l.from_pool_code IN ('sponsor','aid') OR l.to_pool_code IN ('sponsor','aid')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 50"
        );
    }

    /** Filter pill => the ledger reasons it covers (Plan §7.3). */
    public const GROUPS = [
        'escrow'  => ['rental_charge', 'buffer_hold', 'buffer_refund', 'rental_payout', 'booking_refund'],
        'gifts'   => ['gift'],
        'aid'     => ['aid_grant', 'aid_return', 'parting_gift'],
        'fees'    => ['late_fee', 'damage_penalty'],
        'sponsor' => ['sponsor_contribution', 'welcome_bonus', 'moderator_stipend', 'community_reward', 'bond_hold', 'bond_return'],
        'reserve' => ['shortfall_cover', 'reserve_topup', 'bond_forfeit'],
        'closures' => ['account_closure', 'parting_gift', 'recycle'],
    ];

    /** @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int} */
    public function adminList(string $filter, string $search, int $page): array
    {
        $where = '1=1';
        $params = [];

        if (isset(self::GROUPS[$filter])) {
            // A fixed set of placeholders — the values stay bound.
            $names = [];
            foreach (self::GROUPS[$filter] as $index => $reason) {
                $names[]                    = ':reason' . $index;
                $params['reason' . $index] = $reason;
            }
            $where .= ' AND pl.reason IN (' . implode(', ', $names) . ')';
        }

        if ($search !== '') {
            $where .= ' AND (fu.full_name LIKE :q OR tu.full_name LIKE :q2)';
            $params['q'] = "%{$search}%";
            $params['q2'] = "%{$search}%";
        }

        $total = (int) $this->selectValue(
            "SELECT COUNT(*)
               FROM point_ledger pl
               LEFT JOIN users fu ON fu.id = pl.from_user_id
               LEFT JOIN users tu ON tu.id = pl.to_user_id
              WHERE {$where}",
            $params
        );

        $offset = ($page - 1) * self::PER_PAGE;

        $rows = $this->select(
            "SELECT pl.id, pl.from_pool_code, pl.from_user_id, pl.to_pool_code, pl.to_user_id,
                    pl.amount, pl.reason, pl.booking_id, pl.gift_id, pl.aid_grant_id,
                    pl.contribution_id, pl.created_at,
                    fu.full_name AS from_user_name,
                    tu.full_name AS to_user_name
               FROM point_ledger pl
               LEFT JOIN users fu ON fu.id = pl.from_user_id
               LEFT JOIN users tu ON tu.id = pl.to_user_id
              WHERE {$where}
              ORDER BY pl.created_at DESC
              LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
            $params
        );

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => self::PER_PAGE,
        ];
    }

    /**
     * Append one movement. The ledger is INSERT-only (Plan §15.5): there is no
     * update or delete method on this model, and there never will be.
     *
     * Each side is a pool code or a member id, never both. The optional links
     * tie the movement to the booking, gift or aid grant behind it, so the
     * wallet's escrow figure and activity lines can find their record.
     *
     * @param array{from_pool_code:?string, from_user_id:?int, to_pool_code:?string,
     *              to_user_id:?int, amount:int, reason:string, booking_id?:?int,
     *              gift_id?:?int, aid_grant_id?:?int} $entry
     */
    public function record(array $entry): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO point_ledger
                 (from_pool_code, from_user_id, to_pool_code, to_user_id, amount, reason,
                  booking_id, gift_id, aid_grant_id)
             VALUES
                 (:from_pool_code, :from_user_id, :to_pool_code, :to_user_id, :amount, :reason,
                  :booking_id, :gift_id, :aid_grant_id)'
        );

        $statement->execute([
            'from_pool_code' => $entry['from_pool_code'],
            'from_user_id'   => $entry['from_user_id'],
            'to_pool_code'   => $entry['to_pool_code'],
            'to_user_id'     => $entry['to_user_id'],
            'amount'         => $entry['amount'],
            'reason'         => $entry['reason'],
            'booking_id'     => $entry['booking_id'] ?? null,
            'gift_id'        => $entry['gift_id'] ?? null,
            'aid_grant_id'   => $entry['aid_grant_id'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Whether this member has ever received a movement of this kind. */
    public function hasReceived(int $memberId, string $reason): bool
    {
        return (int) $this->selectValue(
            'SELECT COUNT(*) FROM point_ledger WHERE to_user_id = :id AND reason = :reason',
            ['id' => $memberId, 'reason' => $reason]
        ) > 0;
    }
}
