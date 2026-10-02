<?php

declare(strict_types=1);

/**
 * Point movements between the six pools and member wallets (Plan §7.3–7.4).
 *
 * Every movement is three writes that must agree: the append-only ledger row,
 * the source balance and the destination balance. Member wallets are one of
 * the six pools, so a movement into or out of a wallet also moves the
 * 'member_wallets' pool — that keeps SUM(point_pools.balance) equal to the
 * points ever created, which is what the nightly invariant check proves.
 *
 * This service never opens its own transaction: the caller's business step
 * (an approval, a booking acceptance) and its points must commit together, so
 * the caller owns the transaction and this refuses to run outside one (§8).
 * Balances are row-locked before they are checked.
 */
final class LedgerService
{
    /** The pool that mirrors the sum of every member wallet. */
    private const WALLETS_POOL = 'member_wallets';

    public function __construct(
        private PDO $pdo,
        private PointLedger $ledger,
        private PointPool $pools,
        private Wallet $wallets
    ) {
    }

    /**
     * Move points from a pool into one member's wallet.
     *
     * @param array{booking_id?:int, gift_id?:int, aid_grant_id?:int} $links the record behind it
     *
     * @throws InsufficientPointsException when the pool cannot cover it
     * @throws LogicException              when called outside a transaction
     *
     * @return int the ledger entry id
     */
    public function poolToMember(string $poolCode, int $memberId, int $amount, string $reason, array $links = []): int
    {
        $this->guard($amount);

        $available = $this->lockPools([$poolCode, self::WALLETS_POOL])[$poolCode];

        if ($this->wallets->lockBalance($memberId) === null) {
            $this->wallets->openFor($memberId);
            $this->wallets->lockBalance($memberId);
        }

        if ($available < $amount) {
            throw new InsufficientPointsException(sprintf(
                'The %s pool holds %d points; %d are needed.',
                $poolCode,
                $available,
                $amount
            ));
        }

        $this->pools->adjust($poolCode, -$amount);
        $this->pools->adjust(self::WALLETS_POOL, $amount);
        $this->wallets->adjust($memberId, $amount);

        return $this->ledger->record([
            'from_pool_code' => $poolCode,
            'from_user_id'   => null,
            'to_pool_code'   => null,
            'to_user_id'     => $memberId,
            'amount'         => $amount,
            'reason'         => $reason,
        ] + $links);
    }

    /**
     * Move points from one member's wallet into a pool — an account closure
     * (Plan §17) sends the whole spendable balance to Retired or Aid.
     *
     * @param array{booking_id?:int, gift_id?:int, aid_grant_id?:int} $links the record behind it
     *
     * @throws InsufficientPointsException when the wallet cannot cover it
     * @throws LogicException              when called outside a transaction
     *
     * @return int the ledger entry id
     */
    public function memberToPool(int $memberId, string $poolCode, int $amount, string $reason, array $links = []): int
    {
        $this->guard($amount);

        $this->lockPools([$poolCode, self::WALLETS_POOL]);
        $available = $this->wallets->lockBalance($memberId) ?? 0;

        if ($available < $amount) {
            throw new InsufficientPointsException(sprintf(
                'The wallet holds %d points; %d are needed.',
                $available,
                $amount
            ));
        }

        $this->wallets->adjust($memberId, -$amount);
        $this->pools->adjust(self::WALLETS_POOL, -$amount);
        $this->pools->adjust($poolCode, $amount);

        return $this->ledger->record([
            'from_pool_code' => null,
            'from_user_id'   => $memberId,
            'to_pool_code'   => $poolCode,
            'to_user_id'     => null,
            'amount'         => $amount,
            'reason'         => $reason,
        ] + $links);
    }

    /**
     * Move points straight from one wallet to another — a gift, a late fee
     * or a damage penalty. Both wallets stay inside the 'member_wallets' pool,
     * so no pool balance changes; the pool row is still locked first so the
     * lock order matches every other movement.
     *
     * Wallets are locked in id order, the way lockPools() orders pools, so two
     * opposite transfers can never wait on each other.
     *
     * @param array{booking_id?:int, gift_id?:int, aid_grant_id?:int} $links
     *
     * @throws InsufficientPointsException when the sender cannot cover it
     * @throws LogicException              when called outside a transaction
     */
    public function memberToMember(int $fromId, int $toId, int $amount, string $reason, array $links = []): int
    {
        $this->guard($amount);

        if ($fromId === $toId) {
            throw new LogicException('A transfer needs two different wallets.');
        }

        $this->lockPools([self::WALLETS_POOL]);

        $balances = [];
        foreach ([min($fromId, $toId), max($fromId, $toId)] as $id) {
            $balance = $this->wallets->lockBalance($id);

            if ($balance === null) {
                $this->wallets->openFor($id);
                $balance = $this->wallets->lockBalance($id) ?? 0;
            }

            $balances[$id] = $balance;
        }

        if ($balances[$fromId] < $amount) {
            throw new InsufficientPointsException(sprintf(
                'The wallet holds %d points; %d are needed.',
                $balances[$fromId],
                $amount
            ));
        }

        $this->wallets->adjust($fromId, -$amount);
        $this->wallets->adjust($toId, $amount);

        return $this->ledger->record([
            'from_pool_code' => null,
            'from_user_id'   => $fromId,
            'to_pool_code'   => null,
            'to_user_id'     => $toId,
            'amount'         => $amount,
            'reason'         => $reason,
        ] + $links);
    }

    /**
     * Charge a member what they owe another member, without ever creating
     * debt (Plan §7.7): the payer gives what they have, and the Reserve Pool
     * pays the payee the rest as 'shortfall_cover', linked to the booking.
     *
     * @return array{paid: int, covered: int} what the payer moved and what the Reserve added
     *
     * @throws InsufficientPointsException when the Reserve itself cannot cover the gap
     */
    public function chargeWithCover(int $payerId, int $payeeId, int $amount, string $reason, int $bookingId): array
    {
        if ($amount < 1) {
            return ['paid' => 0, 'covered' => 0];
        }

        $this->lockPools(['reserve', self::WALLETS_POOL]);

        // Same id order as memberToMember(), so the two never deadlock.
        $balances = [];
        foreach ([min($payerId, $payeeId), max($payerId, $payeeId)] as $id) {
            $balances[$id] = $this->wallets->lockBalance($id) ?? 0;
        }

        $split = self::shortfallSplit($amount, $balances[$payerId]);

        if ($split['paid'] > 0) {
            $this->memberToMember($payerId, $payeeId, $split['paid'], $reason, ['booking_id' => $bookingId]);
        }

        if ($split['covered'] > 0) {
            $this->poolToMember('reserve', $payeeId, $split['covered'], 'shortfall_cover', ['booking_id' => $bookingId]);
        }

        return $split;
    }

    /**
     * How a charge splits between the payer's wallet and the Reserve Pool.
     * Pure, so the rule is testable without a database.
     *
     * @return array{paid: int, covered: int}
     */
    public static function shortfallSplit(int $amount, int $available): array
    {
        $paid = max(0, min($amount, $available));

        return ['paid' => $paid, 'covered' => max(0, $amount - $paid)];
    }

    /** Whether this member already received a one-off movement, e.g. the welcome bonus. */
    public function hasReceived(int $memberId, string $reason): bool
    {
        return $this->ledger->hasReceived($memberId, $reason);
    }

    /**
     * Lock pool rows in one fixed order (by code), then the wallet, so two
     * movements can never wait on each other's locks.
     *
     * @param list<string> $codes
     *
     * @return array<string, int> code => balance
     */
    private function lockPools(array $codes): array
    {
        $codes = array_values(array_unique($codes));
        sort($codes);

        $balances = [];
        foreach ($codes as $code) {
            $balances[$code] = $this->pools->lockBalance($code);
        }

        return $balances;
    }

    private function guard(int $amount): void
    {
        if (!$this->pdo->inTransaction()) {
            throw new LogicException('Point movements must run inside the caller\'s transaction.');
        }

        // One point is the indivisible unit, and a movement of nothing is a bug (§7.1).
        if ($amount < 1) {
            throw new LogicException('A point movement must move at least one point.');
        }
    }
}
