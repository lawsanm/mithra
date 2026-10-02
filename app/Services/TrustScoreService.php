<?php

declare(strict_types=1);

/**
 * Trust score (Plan 1.6, §6.3) — one number from 0 to 100 shown on profiles,
 * listings and the dashboard.
 *
 *   weighted = 0.40 R + 0.20 V + 0.20 L + 0.10 T + 0.10 C, each factor 0–100
 *     R  ratings received: average stars × 20
 *     V  volume: 10 per completed rental or donation, up to 100
 *     L  on-time returns as a borrower (100 with no returns yet)
 *     T  tenure: months since verification, 100 at 24 months
 *     C  contribution: 20 per completed donation given, up to 100
 *
 *   blended = (10 × 50 + n × weighted) ÷ (10 + n), n = completed transactions,
 *   so a newcomer starts near the community midpoint of 50 and earns their
 *   own score as they take part.
 *
 *   penalties: −5 per upheld claim and −5 per Reserve shortfall cover in the
 *   last 12 months, −10 per past suspension; then clamped to 0–100.
 *
 * The scales for V, T and C are team decisions recorded here (checklist,
 * Phase 4); the weights, the blend and the penalties are the plan's.
 */
final class TrustScoreService
{
    public const WEIGHTS = ['R' => 0.40, 'V' => 0.20, 'L' => 0.20, 'T' => 0.10, 'C' => 0.10];

    /** Transactions a member's own record counts as much as the midpoint. */
    public const PRIOR_WEIGHT = 10;

    public const PRIOR_SCORE = 50;

    public const PER_TRANSACTION = 10;
    public const TENURE_FULL_MONTHS = 24;
    public const PER_DONATION = 20;

    public const UPHELD_CLAIM_PENALTY = 5;
    public const SUSPENSION_PENALTY   = 10;
    public const SHORTFALL_PENALTY    = 5;

    public function __construct(
        private User $users,
        private Rating $ratings,
        private DamageClaim $claims,
        private ShortfallCover $covers,
        private AccountSuspension $suspensions
    ) {
    }

    /**
     * The score from its raw facts. Pure, so every rule is testable.
     *
     * @param array{average_stars: float, ratings: int, completed: int, on_time_percent: int,
     *              months: int, donations: int, upheld_claims: int, suspensions: int,
     *              shortfalls: int} $facts
     *
     * @return array{score: int, weighted: float, blended: float, penalty: int,
     *               factors: array<string, int>}
     */
    public static function compute(array $facts): array
    {
        $factors = [
            'R' => $facts['ratings'] === 0 ? self::PRIOR_SCORE : self::clamp((int) round($facts['average_stars'] * 20)),
            'V' => self::clamp($facts['completed'] * self::PER_TRANSACTION),
            'L' => self::clamp($facts['on_time_percent']),
            'T' => self::clamp((int) floor($facts['months'] * 100 / self::TENURE_FULL_MONTHS)),
            'C' => self::clamp($facts['donations'] * self::PER_DONATION),
        ];

        $weighted = 0.0;
        foreach (self::WEIGHTS as $key => $weight) {
            $weighted += $weight * $factors[$key];
        }

        $n       = max(0, $facts['completed']);
        $blended = (self::PRIOR_WEIGHT * self::PRIOR_SCORE + $n * $weighted) / (self::PRIOR_WEIGHT + $n);
        $penalty = self::UPHELD_CLAIM_PENALTY * $facts['upheld_claims']
            + self::SUSPENSION_PENALTY * $facts['suspensions']
            + self::SHORTFALL_PENALTY * $facts['shortfalls'];

        return [
            'score'    => self::clamp((int) round($blended - $penalty)),
            'weighted' => $weighted,
            'blended'  => $blended,
            'penalty'  => $penalty,
            'factors'  => $factors,
        ];
    }

    /**
     * Work out a member's score from the database and store it on users.
     * Runs after every completed booking, donation and damage resolution, and
     * whenever a rating changes; nightly for everyone (tenure and the 12-month
     * windows move without any event).
     */
    public function recalculate(int $userId): int
    {
        $result = self::compute($this->facts($userId));
        $this->users->setTrustScore($userId, $result['score']);

        return $result['score'];
    }

    /**
     * The breakdown the /trust page shows.
     *
     * @return array{result: array<string, mixed>, facts: array<string, mixed>}
     */
    public function breakdown(int $userId): array
    {
        $facts = $this->facts($userId);

        return ['result' => self::compute($facts), 'facts' => $facts];
    }

    /** scripts/refresh_trust_scores.php: every verified member, nightly. */
    public function refreshAll(): string
    {
        $count = 0;

        foreach ($this->users->activeMemberIds() as $id) {
            $this->recalculate($id);
            $count++;
        }

        return $count . ' trust score' . ($count === 1 ? '' : 's') . ' recalculated';
    }

    /**
     * @return array{average_stars: float, ratings: int, completed: int, on_time_percent: int,
     *               months: int, donations: int, upheld_claims: int, suspensions: int, shortfalls: int}
     */
    private function facts(int $userId): array
    {
        $stats   = $this->users->profileStats($userId);
        $ratings = $this->ratings->summaryFor($userId);
        $joined  = $this->users->joinedAt($userId);
        $tenure  = $joined === null ? null : (new DateTimeImmutable($joined))->diff(new DateTimeImmutable());
        $months  = $tenure === null ? 0 : $tenure->y * 12 + $tenure->m;

        return [
            'average_stars'   => $ratings['average'],
            'ratings'         => $ratings['count'],
            'completed'       => $stats['completed'] + $stats['donations'],
            'on_time_percent' => $stats['on_time'],
            'months'          => $months,
            'donations'       => $stats['donations'],
            'upheld_claims'   => $this->claims->countUpheldAgainst($userId),
            'suspensions'     => $this->suspensions->countPastFor($userId),
            'shortfalls'      => $this->covers->countAgainstWithinYear($userId),
        ];
    }

    private static function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }
}
