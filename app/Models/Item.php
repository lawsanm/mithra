<?php

declare(strict_types=1);

/**
 * items — Browse, Item Detail and My Items.
 */
final class Item extends BaseModel
{
    protected string $table = 'items';
    protected string $columns = 'id, owner_id, category_id, title, listing_type, declared_value, daily_rate, monthly_rate, status';

    /**
     * Browse: everyone else's listable items in a division, optionally filtered.
     *
     * @return list<array<string, mixed>>
     */
    public function browse(int $divisionId, int $excludeOwnerId, ?int $categoryId, string $query): array
    {
        $sql = "SELECT i.id, i.title, i.daily_rate, i.status,
                       u.full_name AS owner_name, u.trust_score,
                       (SELECT MIN(b.end_date) FROM bookings b
                         WHERE b.item_id = i.id AND b.status IN ('in_progress','awaiting_return')) AS back_on
                  FROM items i
                  JOIN users u ON u.id = i.owner_id
                 WHERE i.gn_division_id = :division
                   AND i.owner_id <> :owner
                   AND i.listing_type = 'rental'
                   AND i.status IN ('active','borrowed')";

        $params = ['division' => $divisionId, 'owner' => $excludeOwnerId];

        if ($categoryId !== null) {
            $sql .= ' AND i.category_id = :category';
            $params['category'] = $categoryId;
        }

        if ($query !== '') {
            // Native prepares forbid reusing one placeholder, so bind twice.
            $sql .= ' AND (i.title LIKE :q_title OR i.description LIKE :q_body)';
            $params['q_title'] = '%' . $query . '%';
            $params['q_body']  = '%' . $query . '%';
        }

        return $this->select($sql . ' ORDER BY i.id', $params);
    }

    public function countAvailableIn(int $divisionId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM items
              WHERE gn_division_id = :d AND listing_type = 'rental' AND status IN ('active','borrowed')",
            ['d' => $divisionId]
        );
    }

    /**
     * Item Detail, including its owner's standing.
     *
     * @return array<string, mixed>|null
     */
    public function findForDetail(int $id): ?array
    {
        return $this->selectOne(
            "SELECT i.id, i.title, i.description, i.declared_value, i.daily_rate, i.monthly_rate,
                    i.status, i.listing_type,
                    c.name AS category_name, c.id AS category_id,
                    u.id AS owner_id, u.full_name AS owner_name, u.trust_score,
                    u.joined_at AS owner_joined, u.status AS owner_status,
                    (SELECT COUNT(*) FROM bookings b WHERE b.lender_id = u.id AND b.status = 'completed')
                      AS owner_lends
               FROM items i
               JOIN item_categories c ON c.id = i.category_id
               JOIN users u           ON u.id = i.owner_id
              WHERE i.id = :id",
            ['id' => $id]
        );
    }

    /**
     * My Items, with the live booking that explains a "borrowed" status.
     *
     * @return list<array<string, mixed>>
     */
    public function ownedBy(int $ownerId, ?string $type = null): array
    {
        $sql = "SELECT i.id, i.title, i.listing_type, i.daily_rate, i.declared_value, i.status, i.created_at,
                       (SELECT MIN(b.end_date) FROM bookings b
                         WHERE b.item_id = i.id AND b.status IN ('in_progress','awaiting_return')) AS due_back,
                       (SELECT COUNT(*) FROM donation_requests dr
                          JOIN donations d ON d.id = dr.donation_id
                         WHERE d.item_id = i.id AND dr.status = 'pending') AS request_count
                  FROM items i
                 WHERE i.owner_id = :owner AND i.status <> 'archived'";

        $params = ['owner' => $ownerId];

        if ($type !== null) {
            $sql .= ' AND i.listing_type = :type';
            $params['type'] = $type;
        }

        return $this->select($sql . ' ORDER BY i.id', $params);
    }

    /**
     * @return array{all:int, rental:int, donation:int}
     */
    public function ownedCounts(int $ownerId): array
    {
        $row = $this->selectOne(
            "SELECT COUNT(*) AS all_count,
                    SUM(listing_type = 'rental')   AS rental_count,
                    SUM(listing_type = 'donation') AS donation_count
               FROM items WHERE owner_id = :owner AND status <> 'archived'",
            ['owner' => $ownerId]
        ) ?? [];

        return [
            'all'      => (int) ($row['all_count'] ?? 0),
            'rental'   => (int) ($row['rental_count'] ?? 0),
            'donation' => (int) ($row['donation_count'] ?? 0),
        ];
    }

    public function countLentOut(int $ownerId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM items WHERE owner_id = :owner AND status = 'borrowed'",
            ['owner' => $ownerId]
        );
    }

    /**
     * Dashboard: the member's own listings, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function recentListings(int $ownerId, int $limit = 4): array
    {
        $statement = $this->pdo->prepare(
            "SELECT i.id, i.title, i.daily_rate, i.status,
                    (SELECT CONCAT(u.full_name, '|', b.end_date) FROM bookings b
                       JOIN users u ON u.id = b.borrower_id
                      WHERE b.item_id = i.id AND b.status IN ('in_progress','awaiting_return')
                      LIMIT 1) AS lent_to
               FROM items i
              WHERE i.owner_id = :owner AND i.listing_type = 'rental' AND i.status <> 'archived'
              ORDER BY i.id
              LIMIT :limit"
        );
        $statement->bindValue(':owner', $ownerId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
