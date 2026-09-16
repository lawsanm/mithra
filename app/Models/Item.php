<?php

declare(strict_types=1);

/**
 * items — Browse, Item Detail and My Items.
 *
 * Rows are never deleted: a member archives a listing and the row stays, since
 * bookings reference it (Rules/CONVENTIONS.md §6).
 */
final class Item extends BaseModel
{
    protected string $table = 'items';
    protected string $columns = 'id, owner_id, category_id, title, listing_type, declared_value, daily_rate, monthly_rate, status';

    /** Browse page size (§9 — no unbounded result sets). */
    public const PER_PAGE = 20;

    /**
     * Browse: everyone else's listable items in a division, optionally filtered.
     *
     * @return list<array<string, mixed>>
     */
    public function browse(int $divisionId, int $excludeOwnerId, ?int $categoryId, string $query, int $page = 1): array
    {
        // JSON_UNQUOTE(JSON_EXTRACT(...)) rather than the ->> shorthand: the
        // shorthand is MySQL-only and the team's XAMPP boxes run MariaDB.
        $sql = "SELECT i.id, i.title, i.daily_rate, i.monthly_rate, i.status,
                       JSON_UNQUOTE(JSON_EXTRACT(i.photos, '$[0]')) AS photo,
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

        // LIMIT/OFFSET must bind as integers, which execute($params) cannot do.
        $statement = $this->pdo->prepare($sql . ' ORDER BY i.id DESC LIMIT :take OFFSET :skip');

        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }

        $statement->bindValue(':take', self::PER_PAGE, PDO::PARAM_INT);
        $statement->bindValue(':skip', (max(1, $page) - 1) * self::PER_PAGE, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Total rows behind the current browse filters, for the result count.
     */
    public function countBrowse(int $divisionId, int $excludeOwnerId, ?int $categoryId, string $query): int
    {
        $sql = "SELECT COUNT(*)
                  FROM items i
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
            $sql .= ' AND (i.title LIKE :q_title OR i.description LIKE :q_body)';
            $params['q_title'] = '%' . $query . '%';
            $params['q_body']  = '%' . $query . '%';
        }

        return (int) $this->selectValue($sql, $params);
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
                    i.status, i.listing_type, i.photos, i.gn_division_id,
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
     * The full row for an owner-only screen. Filtering by owner in SQL is the
     * ownership check itself — a wrong id simply finds nothing (§8).
     *
     * @return array<string, mixed>|null
     */
    public function findOwned(int $id, int $ownerId): ?array
    {
        return $this->selectOne(
            'SELECT id, owner_id, gn_division_id, category_id, title, description, listing_type,
                    declared_value, value_proof_type, value_proof_path, photos,
                    daily_rate, monthly_rate, status, created_at
               FROM items
              WHERE id = :id AND owner_id = :owner',
            ['id' => $id, 'owner' => $ownerId]
        );
    }

    /**
     * My Items, with the live booking that explains a "borrowed" status.
     *
     * @return list<array<string, mixed>>
     */
    public function ownedBy(int $ownerId, ?string $type = null): array
    {
        $sql = "SELECT i.id, i.title, i.listing_type, i.daily_rate, i.monthly_rate,
                       i.declared_value, i.status, i.created_at,
                       JSON_UNQUOTE(JSON_EXTRACT(i.photos, '$[0]')) AS photo,
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

        return $this->select($sql . ' ORDER BY i.id DESC', $params);
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

    /**
     * Insert a new listing and return its id. Callers pass an already-validated
     * set — this method makes no business decisions (§6).
     *
     * @param array{owner_id:int, gn_division_id:int, category_id:int, title:string,
     *              description:?string, listing_type:string, declared_value:int,
     *              value_proof_type:?string, value_proof_path:?string, photos:list<string>,
     *              daily_rate:?int, monthly_rate:?int} $data
     */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO items
                 (owner_id, gn_division_id, category_id, title, description, listing_type,
                  declared_value, value_proof_type, value_proof_path, photos,
                  daily_rate, monthly_rate, status)
             VALUES
                 (:owner_id, :gn_division_id, :category_id, :title, :description, :listing_type,
                  :declared_value, :value_proof_type, :value_proof_path, :photos,
                  :daily_rate, :monthly_rate, 'pending_approval')"
        );

        $statement->execute([
            'owner_id'         => $data['owner_id'],
            'gn_division_id'   => $data['gn_division_id'],
            'category_id'      => $data['category_id'],
            'title'            => $data['title'],
            'description'      => $data['description'],
            'listing_type'     => $data['listing_type'],
            'declared_value'   => $data['declared_value'],
            'value_proof_type' => $data['value_proof_type'],
            'value_proof_path' => $data['value_proof_path'],
            'photos'           => json_encode($data['photos']),
            'daily_rate'       => $data['daily_rate'],
            'monthly_rate'     => $data['monthly_rate'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update the editable fields of one listing. The owner is part of the WHERE
     * clause, so a forged id changes nothing.
     *
     * @param array{category_id:int, title:string, description:?string, listing_type:string,
     *              declared_value:int, photos:list<string>, daily_rate:?int,
     *              monthly_rate:?int, status:string} $data
     */
    public function updateOwned(int $id, int $ownerId, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE items
                SET category_id    = :category_id,
                    title          = :title,
                    description    = :description,
                    listing_type   = :listing_type,
                    declared_value = :declared_value,
                    value_proof_type = :value_proof_type,
                    value_proof_path = :value_proof_path,
                    photos         = :photos,
                    daily_rate     = :daily_rate,
                    monthly_rate   = :monthly_rate,
                    status         = :status,
                    value_status   = IF(:requeued = 1, \'pending\', value_status)
              WHERE id = :id AND owner_id = :owner'
        );

        $statement->execute([
            'category_id'    => $data['category_id'],
            'title'          => $data['title'],
            'description'    => $data['description'],
            'listing_type'   => $data['listing_type'],
            'declared_value' => $data['declared_value'],
            'value_proof_type' => $data['value_proof_type'],
            'value_proof_path' => $data['value_proof_path'],
            'photos'         => json_encode($data['photos']),
            'daily_rate'     => $data['daily_rate'],
            'monthly_rate'   => $data['monthly_rate'],
            'status'         => $data['status'],
            'requeued'       => $data['status'] === 'pending_approval' ? 1 : 0,
            'id'             => $id,
            'owner'          => $ownerId,
        ]);
    }

    /**
     * Move one listing to another status — pause, resume, archive.
     */
    public function updateOwnedStatus(int $id, int $ownerId, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE items SET status = :status WHERE id = :id AND owner_id = :owner'
        );

        $statement->execute(['status' => $status, 'id' => $id, 'owner' => $ownerId]);
    }

    /**
     * Is this stored path referenced by a live listing? The photo proxy asks
     * before serving a file, so storage cannot be enumerated (§7.5).
     */
    public function photoPathExists(string $path): bool
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM items
              WHERE JSON_CONTAINS(photos, JSON_QUOTE(:path)) AND status <> 'archived'",
            ['path' => $path]
        ) > 0;
    }

    /**
     * Who may see a listing's proof of value: its owner and its division's
     * moderator. Null when no listing carries this proof.
     *
     * @return list<int>|null
     */
    public function valueProofViewers(string $path): ?array
    {
        $row = $this->selectOne(
            'SELECT i.owner_id, d.moderator_id
               FROM items i
               JOIN gn_divisions d ON d.id = i.gn_division_id
              WHERE i.value_proof_path = :path
              LIMIT 1',
            ['path' => $path]
        );

        if ($row === null) {
            return null;
        }

        return array_values(array_filter(
            [(int) $row['owner_id'], (int) ($row['moderator_id'] ?? 0)],
            static fn (int $id): bool => $id > 0
        ));
    }

    /**
     * Listings awaiting (or past) a value review, with who listed them and
     * whether that person moderates the division — the fact that decides who
     * may review it (Plan §16.5).
     *
     * @param list<int> $divisionIds divisions to look in; empty means every division
     * @param string    $state       '' | 'pending' | 'approved' | 'rejected'
     *
     * @return list<array<string, mixed>>
     */
    public function reviewQueue(array $divisionIds, string $state): array
    {
        $params = [];
        $where  = [];

        if ($divisionIds !== []) {
            $names = [];
            foreach (array_values($divisionIds) as $index => $divisionId) {
                $names[]                 = ':division' . $index;
                $params['division' . $index] = $divisionId;
            }
            $where[] = 'i.gn_division_id IN (' . implode(', ', $names) . ')';
        }

        $where[] = match ($state) {
            'pending'  => "i.status = 'pending_approval'",
            'approved' => "i.value_status IN ('validated','adjusted')",
            'rejected' => "i.status = 'rejected'",
            default    => "(i.status = 'pending_approval' OR i.value_status <> 'pending')",
        };

        return $this->select(
            'SELECT i.id, i.title, i.declared_value, i.value_proof_type, i.status, i.value_status,
                    i.created_at, i.updated_at, i.owner_id, i.gn_division_id,
                    JSON_UNQUOTE(JSON_EXTRACT(i.photos, \'$[0]\')) AS photo,
                    u.full_name AS owner_name,
                    d.name AS division_name, d.moderator_id
               FROM items i
               JOIN users u        ON u.id = i.owner_id
               JOIN gn_divisions d ON d.id = i.gn_division_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY (i.status = \'pending_approval\') DESC, i.updated_at ASC
              LIMIT ' . self::PER_PAGE,
            $params
        );
    }

    /**
     * One listing as the reviewer sees it, proof included.
     *
     * @return array<string, mixed>|null
     */
    public function findForReview(int $id): ?array
    {
        return $this->selectOne(
            'SELECT i.id, i.title, i.description, i.listing_type, i.declared_value,
                    i.value_proof_type, i.value_proof_path, i.photos, i.daily_rate, i.monthly_rate,
                    i.status, i.value_status, i.created_at, i.owner_id, i.gn_division_id,
                    c.name AS category_name,
                    u.full_name AS owner_name, u.trust_score,
                    d.name AS division_name, d.moderator_id
               FROM items i
               JOIN item_categories c ON c.id = i.category_id
               JOIN users u           ON u.id = i.owner_id
               JOIN gn_divisions d    ON d.id = i.gn_division_id
              WHERE i.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Record a reviewer's decision on a listing still awaiting one. The status
     * guard makes a second, concurrent decision a no-op.
     *
     * @return bool false when the listing was no longer pending
     */
    public function recordReview(int $id, string $status, string $valueStatus, int $declaredValue, int $reviewerId): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE items
                SET status = :status, value_status = :value_status, declared_value = :declared_value,
                    approved_by = :reviewer, approved_at = NOW()
              WHERE id = :id AND status = 'pending_approval'"
        );

        $statement->execute([
            'status'         => $status,
            'value_status'   => $valueStatus,
            'declared_value' => $declaredValue,
            'reviewer'       => $reviewerId,
            'id'             => $id,
        ]);

        return $statement->rowCount() === 1;
    }

    /** Take every live or queued listing of a closing account off the shelf. */
    public function archiveAllFor(int $ownerId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE items SET status = 'archived'
              WHERE owner_id = :owner AND status IN ('pending_approval','active','paused','rejected')"
        );
        $statement->execute(['owner' => $ownerId]);
    }
}
