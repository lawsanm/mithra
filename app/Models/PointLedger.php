<?php

declare(strict_types=1);

final class PointLedger extends BaseModel
{
    protected string $table = 'point_ledger';
    protected string $columns = 'id, from_pool_code, from_user_id, to_pool_code, to_user_id, amount, reason, created_at';

    private const PER_PAGE = 25;

    /** @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int} */
    public function adminList(string $filter, string $search, int $page): array
    {
        $where = '1=1';
        $params = [];

        if ($filter !== '' && $filter !== 'all') {
            $where .= ' AND pl.reason = :reason';
            $params['reason'] = $filter;
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
                    pl.sponsor_purchase_id, pl.created_at,
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
}
