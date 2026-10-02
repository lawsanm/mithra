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
    public function browse(int $divisionId, int $excludeOwnerId, ?int $categoryId, string $query, int $page = 1, ?string $type = null): array
    {
        // JSON_UNQUOTE(JSON_EXTRACT(...)) rather than the ->> shorthand: the
        // shorthand is MySQL-only and the team's XAMPP boxes run MariaDB.
        [$where, $params] = self::browseWhere($divisionId, $excludeOwnerId, $categoryId, $query, $type);

        return $this->selectPage(
            "SELECT i.id, i.title, i.listing_type, i.daily_rate, i.monthly_rate, i.status,
                    JSON_UNQUOTE(JSON_EXTRACT(i.photos, '$[0]')) AS photo,
                    u.full_name AS owner_name, u.trust_score,
                    (SELECT MIN(b.end_date) FROM bookings b
                      WHERE b.item_id = i.id AND b.status IN ('in_progress','awaiting_return')) AS back_on
               FROM items i
               JOIN users u ON u.id = i.owner_id
              WHERE {$where}
              ORDER BY i.id DESC",
            $params,
            $page,
            self::PER_PAGE
        );
    }

    /**
     * Total rows behind the current browse filters, for the result count.
     */
    public function countBrowse(int $divisionId, int $excludeOwnerId, ?int $categoryId, string $query, ?string $type = null): int
    {
        [$where, $params] = self::browseWhere($divisionId, $excludeOwnerId, $categoryId, $query, $type);

        return (int) $this->selectValue("SELECT COUNT(*) FROM items i WHERE {$where}", $params);
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
                      AS owner_lends,
                    (SELECT MIN(b.end_date) FROM bookings b
                      WHERE b.item_id = i.id AND b.status IN ('in_progress','awaiting_return')) AS due_back
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
                         WHERE d.item_id = i.id AND dr.status = 'pending') AS request_count,
                       (SELECT MAX(d.id) FROM donations d WHERE d.item_id = i.id) AS donation_id
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
        return $this->selectPage(
            "SELECT i.id, i.title, i.daily_rate, i.monthly_rate, i.status,
                    JSON_UNQUOTE(JSON_EXTRACT(i.photos, '$[0]')) AS photo,
                    (SELECT CONCAT(u.full_name, '|', b.end_date) FROM bookings b
                       JOIN users u ON u.id = b.borrower_id
                      WHERE b.item_id = i.id AND b.status IN ('in_progress','awaiting_return')
                      LIMIT 1) AS lent_to
               FROM items i
              WHERE i.owner_id = :owner AND i.listing_type = 'rental' AND i.status <> 'archived'
              ORDER BY i.id DESC",
            ['owner' => $ownerId],
            1,
            $limit
        );
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
        return $this->insert(
            "INSERT INTO items
                 (owner_id, gn_division_id, category_id, title, description, listing_type,
                  declared_value, value_proof_type, value_proof_path, photos,
                  daily_rate, monthly_rate, status)
             VALUES
                 (:owner_id, :gn_division_id, :category_id, :title, :description, :listing_type,
                  :declared_value, :value_proof_type, :value_proof_path, :photos,
                  :daily_rate, :monthly_rate, 'pending_approval')",
            ['photos' => json_encode($data['photos'], JSON_UNESCAPED_SLASHES)] + $data
        );
    }

    /**
     * Update the editable fields of one listing. The owner is part of the WHERE
     * clause, so a forged id changes nothing.
     *
     * @param array{category_id:int, title:string, description:?string, listing_type:string,
     *              declared_value:int, value_proof_type:?string, value_proof_path:?string,
     *              photos:list<string>, daily_rate:?int, monthly_rate:?int, status:string} $data
     */
    public function updateOwned(int $id, int $ownerId, array $data): void
    {
        $this->execute(
            "UPDATE items
                SET category_id = :category_id, title = :title, description = :description,
                    listing_type = :listing_type, declared_value = :declared_value,
                    value_proof_type = :value_proof_type, value_proof_path = :value_proof_path,
                    photos = :photos, daily_rate = :daily_rate, monthly_rate = :monthly_rate,
                    status = :status, value_status = IF(:requeued = 1, 'pending', value_status)
              WHERE id = :id AND owner_id = :owner",
            [
                'photos'   => json_encode($data['photos'], JSON_UNESCAPED_SLASHES),
                'requeued' => $data['status'] === 'pending_approval' ? 1 : 0,
                'id'       => $id,
                'owner'    => $ownerId,
            ] + $data
        );
    }

    /**
     * Move one listing to another status — pause, resume, archive.
     */
    public function updateOwnedStatus(int $id, int $ownerId, string $status): void
    {
        $this->execute('UPDATE items SET status = :status WHERE id = :id AND owner_id = :owner', ['status' => $status, 'id' => $id, 'owner' => $ownerId]);
    }

    /** Listings a moderator approved that are still on offer, out on loan or already given away. */
    public function countShared(): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM items WHERE status IN ('active', 'paused', 'borrowed', 'donated')"
        );
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

        return $row === null ? null : self::ids($row);
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
        $where  = [match ($state) {
            'pending'  => "i.status = 'pending_approval'",
            'approved' => "i.value_status IN ('validated','adjusted')",
            'rejected' => "i.status = 'rejected'",
            default    => "(i.status = 'pending_approval' OR i.value_status <> 'pending')",
        }];

        if ($divisionIds !== []) {
            $where[] = 'i.gn_division_id IN ' . self::inList('division', $divisionIds, $params);
        }

        return $this->selectPage(
            "SELECT i.id, i.title, i.declared_value, i.value_proof_type, i.status, i.value_status,
                    i.created_at, i.updated_at, i.owner_id, i.gn_division_id,
                    JSON_UNQUOTE(JSON_EXTRACT(i.photos, '$[0]')) AS photo,
                    u.full_name AS owner_name,
                    d.name AS division_name, d.moderator_id
               FROM items i
               JOIN users u        ON u.id = i.owner_id
               JOIN gn_divisions d ON d.id = i.gn_division_id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY (i.status = 'pending_approval') DESC, i.updated_at ASC",
            $params,
            1,
            self::PER_PAGE
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
        return $this->execute(
            "UPDATE items
                SET status = :status, value_status = :value_status, declared_value = :declared_value,
                    approved_by = :reviewer, approved_at = NOW()
              WHERE id = :id AND status = 'pending_approval'",
            ['status' => $status, 'value_status' => $valueStatus, 'declared_value' => $declaredValue, 'reviewer' => $reviewerId, 'id' => $id]
        ) === 1;
    }

    /** Take every live or queued listing of a closing account off the shelf. */
    public function archiveAllFor(int $ownerId): void
    {
        $this->execute(
            "UPDATE items SET status = 'archived'
              WHERE owner_id = :owner AND status IN ('pending_approval','active','paused','rejected')",
            ['owner' => $ownerId]
        );
    }

    /**
     * Take a member's live listings in one division off the shelf — their
     * temporary membership there ended or their home moved (Plan §6.5). A
     * listing out on loan keeps running; it is paused by the owner later.
     */
    public function pauseAllInDivision(int $ownerId, int $divisionId): void
    {
        $this->execute(
            "UPDATE items SET status = 'paused'
              WHERE owner_id = :owner AND gn_division_id = :division AND status = 'active'",
            ['owner' => $ownerId, 'division' => $divisionId]
        );
    }

    /**
     * Lock one listing row until the transaction ends. Accepting a booking
     * takes this lock first, so two acceptances on the same item queue up
     * and the second sees the first's dates.
     */
    public function lockRow(int $id): void
    {
        $this->selectValue('SELECT id FROM items WHERE id = :id FOR UPDATE', ['id' => $id]);
    }

    /** The item is back from a completed loan and on the shelf again. */
    public function returnToShelf(int $id): void
    {
        $this->execute("UPDATE items SET status = 'active' WHERE id = :id AND status = 'borrowed'", ['id' => $id]);
    }

    /**
     * The WHERE clause Browse and its count share: other members' listable
     * items in one division, narrowed by the filters.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function browseWhere(int $divisionId, int $excludeOwnerId, ?int $categoryId, string $query, ?string $type): array
    {
        $where  = "i.gn_division_id = :division AND i.owner_id <> :owner
                   AND ((i.listing_type = 'rental' AND i.status IN ('active','borrowed'))
                     OR (i.listing_type = 'donation' AND i.status = 'active'))";
        $params = ['division' => $divisionId, 'owner' => $excludeOwnerId];

        if ($type !== null) {
            $where .= ' AND i.listing_type = :type';
            $params['type'] = $type;
        }

        if ($categoryId !== null) {
            $where .= ' AND i.category_id = :category';
            $params['category'] = $categoryId;
        }

        if ($query !== '') {
            // Native prepares forbid reusing one placeholder, so bind twice.
            $where .= ' AND (i.title LIKE :q_title OR i.description LIKE :q_body)';
            $params['q_title'] = '%' . $query . '%';
            $params['q_body']  = '%' . $query . '%';
        }

        return [$where, $params];
    }
}
