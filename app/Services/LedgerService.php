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
     * @throws InsufficientPointsException when the pool cannot cover it
     * @throws LogicException              when called outside a transaction
     *
     * @return int the ledger entry id
     */
    public function poolToMember(string $poolCode, int $memberId, int $amount, string $reason): int
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
        ]);
    }

    /**
     * Move points from one member's wallet into a pool — an account closure
     * (Plan §17) sends the whole spendable balance to Retired or Aid.
     *
     * @throws InsufficientPointsException when the wallet cannot cover it
     * @throws LogicException              when called outside a transaction
     *
     * @return int the ledger entry id
     */
    public function memberToPool(int $memberId, string $poolCode, int $amount, string $reason): int
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
        ]);
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
