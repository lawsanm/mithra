<?php

declare(strict_types=1);

final class Donation extends BaseModel
{
    protected string $table = 'donations';
    protected string $columns = 'id, item_id, donor_id, recipient_id, selection_mode, status, created_at';

    public function forParticipant(int $id, int $memberId): ?array
    {
        return $this->selectOne(
            'SELECT d.id, d.donor_id, d.recipient_id, d.selection_mode, d.status, d.created_at,
                    i.title, u.full_name AS recipient_name, u.trust_score,
                    JSON_UNQUOTE(JSON_EXTRACT(i.photos, \'$[0]\')) AS photo
               FROM donations d JOIN items i ON i.id = d.item_id
          LEFT JOIN users u ON u.id = d.recipient_id
              WHERE d.id = :id AND (d.donor_id = :donor OR d.recipient_id = :recipient)',
            ['id' => $id, 'donor' => $memberId, 'recipient' => $memberId]
        );
    }

    public function requests(int $id, int $donorId): array
    {
        return $this->select(
            "SELECT r.id, r.requester_id, r.message, r.status, r.requested_at,
                    u.full_name, u.trust_score, gd.name AS division_name
               FROM donation_requests r JOIN donations d ON d.id = r.donation_id
               JOIN users u ON u.id = r.requester_id
          LEFT JOIN user_divisions ud ON ud.user_id = u.id AND ud.membership_type = 'home'
          LEFT JOIN gn_divisions gd ON gd.id = ud.gn_division_id
              WHERE d.id = :id AND d.donor_id = :donor ORDER BY r.requested_at",
            ['id' => $id, 'donor' => $donorId]
        );
    }

    /**
     * The donation behind an item, newest first — what the item page offers.
     *
     * @return array<string, mixed>|null
     */
    public function latestForItem(int $itemId): ?array
    {
        return $this->selectOne(
            'SELECT id, item_id, donor_id, recipient_id, selection_mode, status
               FROM donations WHERE item_id = :item ORDER BY id DESC LIMIT 1',
            ['item' => $itemId]
        );
    }

    /**
     * One donation with its item, row-locked for the rest of the transaction
     * so two requests or two selections cannot both win.
     *
     * @return array<string, mixed>|null
     */
    public function lockWithItem(int $id): ?array
    {
        return $this->selectOne(
            'SELECT d.id, d.item_id, d.donor_id, d.recipient_id, d.selection_mode, d.status,
                    d.donor_confirmed_at, d.recipient_confirmed_at,
                    i.title, i.status AS item_status, i.gn_division_id
               FROM donations d JOIN items i ON i.id = d.item_id
              WHERE d.id = :id
              FOR UPDATE',
            ['id' => $id]
        );
    }

    /** Open the donation for a newly approved donation listing (Plan §13.1). */
    public function openFor(int $itemId, int $donorId): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO donations (item_id, donor_id, selection_mode, status)
             VALUES (:item, :donor, 'donor_chooses', 'open')"
        );
        $statement->execute(['item' => $itemId, 'donor' => $donorId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function hasLiveForItem(int $itemId): bool
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM donations WHERE item_id = :item AND status IN ('open','recipient_selected')",
            ['item' => $itemId]
        ) > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function requestBy(int $donationId, int $requesterId): ?array
    {
        return $this->selectOne(
            'SELECT id, donation_id, requester_id, message, status, requested_at
               FROM donation_requests WHERE donation_id = :donation AND requester_id = :requester',
            ['donation' => $donationId, 'requester' => $requesterId]
        );
    }

    /**
     * One request with its donation, for the requester's withdraw.
     *
     * @return array<string, mixed>|null
     */
    public function findRequest(int $requestId): ?array
    {
        return $this->selectOne(
            'SELECT r.id, r.donation_id, r.requester_id, r.status, d.donor_id, d.status AS donation_status, d.item_id
               FROM donation_requests r JOIN donations d ON d.id = r.donation_id
              WHERE r.id = :id',
            ['id' => $requestId]
        );
    }

    /**
     * Ask for a donation. A member who withdrew may ask again on the same row,
     * since uk_dr_once allows one row per member.
     */
    public function saveRequest(int $donationId, int $requesterId, ?string $message): int
    {
        $existing = $this->requestBy($donationId, $requesterId);

        if ($existing !== null) {
            $statement = $this->pdo->prepare(
                "UPDATE donation_requests SET status = 'pending', message = :message, requested_at = NOW()
                  WHERE id = :id AND status = 'withdrawn'"
            );
            $statement->execute(['message' => $message, 'id' => $existing['id']]);

            return (int) $existing['id'];
        }

        $statement = $this->pdo->prepare(
            "INSERT INTO donation_requests (donation_id, requester_id, message, status)
             VALUES (:donation, :requester, :message, 'pending')"
        );
        $statement->execute(['donation' => $donationId, 'requester' => $requesterId, 'message' => $message]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setRequestStatus(int $requestId, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE donation_requests SET status = :status WHERE id = :id');
        $statement->execute(['status' => $status, 'id' => $requestId]);
    }

    /**
     * Decline every pending or selected request except one (0 for all), and
     * return who was declined so they can be told.
     *
     * @return list<int> requester ids
     */
    public function declineOthers(int $donationId, int $keepRequestId): array
    {
        $rows = $this->select(
            "SELECT requester_id FROM donation_requests
              WHERE donation_id = :donation AND id <> :keep AND status IN ('pending','selected')",
            ['donation' => $donationId, 'keep' => $keepRequestId]
        );

        $statement = $this->pdo->prepare(
            "UPDATE donation_requests SET status = 'declined'
              WHERE donation_id = :donation AND id <> :keep AND status IN ('pending','selected')"
        );
        $statement->execute(['donation' => $donationId, 'keep' => $keepRequestId]);

        return array_map(static fn (array $row): int => (int) $row['requester_id'], $rows);
    }

    public function countPendingRequests(int $donationId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM donation_requests WHERE donation_id = :donation AND status = 'pending'",
            ['donation' => $donationId]
        );
    }

    /**
     * The oldest pending request — who first-come mode hands the item to.
     *
     * @return array<string, mixed>|null
     */
    public function firstPendingRequest(int $donationId): ?array
    {
        return $this->selectOne(
            "SELECT id, requester_id FROM donation_requests
              WHERE donation_id = :donation AND status = 'pending'
              ORDER BY requested_at, id LIMIT 1",
            ['donation' => $donationId]
        );
    }

    public function setMode(int $id, string $mode): void
    {
        $statement = $this->pdo->prepare('UPDATE donations SET selection_mode = :mode WHERE id = :id');
        $statement->execute(['mode' => $mode, 'id' => $id]);
    }

    /** Point the donation at a recipient, or back at nobody (null) after a withdrawal. */
    public function setRecipient(int $id, ?int $recipientId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE donations
                SET recipient_id = :recipient,
                    status = IF(:selected = 1, 'recipient_selected', 'open'),
                    donor_confirmed_at = NULL, recipient_confirmed_at = NULL
              WHERE id = :id"
        );
        $statement->execute(['recipient' => $recipientId, 'selected' => $recipientId === null ? 0 : 1, 'id' => $id]);
    }

    /** One side's handover confirmation (Plan §13.1 step 4). */
    public function confirmSide(int $id, string $side): void
    {
        $column = $side === 'donor' ? 'donor_confirmed_at' : 'recipient_confirmed_at';

        $statement = $this->pdo->prepare("UPDATE donations SET {$column} = COALESCE({$column}, NOW()) WHERE id = :id");
        $statement->execute(['id' => $id]);
    }

    public function complete(int $id): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE donations SET status = 'completed', handover_at = NOW() WHERE id = :id"
        );
        $statement->execute(['id' => $id]);
    }

    public function cancel(int $id): void
    {
        $statement = $this->pdo->prepare("UPDATE donations SET status = 'cancelled' WHERE id = :id");
        $statement->execute(['id' => $id]);
    }

    /**
     * The donation as the handover page shows it, to its donor or recipient.
     *
     * @return array<string, mixed>|null
     */
    public function forHandover(int $id): ?array
    {
        return $this->selectOne(
            'SELECT d.id, d.item_id, d.donor_id, d.recipient_id, d.status, d.handover_at,
                    d.donor_confirmed_at, d.recipient_confirmed_at,
                    i.title, JSON_UNQUOTE(JSON_EXTRACT(i.photos, \'$[0]\')) AS photo,
                    donor.full_name AS donor_name, donor.trust_score AS donor_trust,
                    r.full_name AS recipient_name, r.trust_score AS recipient_trust
               FROM donations d
               JOIN items i ON i.id = d.item_id
               JOIN users donor ON donor.id = d.donor_id
          LEFT JOIN users r ON r.id = d.recipient_id
              WHERE d.id = :id',
            ['id' => $id]
        );
    }

    /** Completed donations given — the donor badge and the trust score's contribution factor. */
    public function countCompletedBy(int $donorId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM donations WHERE donor_id = :donor AND status = 'completed'",
            ['donor' => $donorId]
        );
    }

    /** Donations not yet handed over that this member is part of — account closure waits for them. */
    public function countUnfinishedFor(int $memberId): int
    {
        return (int) $this->selectValue(
            "SELECT COUNT(*) FROM donations
              WHERE status = 'recipient_selected' AND (donor_id = :donor OR recipient_id = :recipient)",
            ['donor' => $memberId, 'recipient' => $memberId]
        );
    }
}
