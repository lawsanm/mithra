<?php

declare(strict_types=1);

/**
 * GET /trust — how the member's trust score is built (Plan 1.6, §6.3).
 */
final class TrustController extends Controller
{
    /** Shared with every service that recalculates after an event, and the nightly job. */
    public static function service(PDO $pdo): TrustScoreService
    {
        return new TrustScoreService(
            new User($pdo),
            new Rating($pdo),
            new DamageClaim($pdo),
            new ShortfallCover($pdo),
            new AccountSuspension($pdo)
        );
    }

    public function show(): void
    {
        $me        = $this->userId();
        $member    = (new User($this->pdo))->findWithDivision($me) ?? [];
        $breakdown = self::service($this->pdo)->breakdown($me);
        $result    = $breakdown['result'];
        $facts     = $breakdown['facts'];

        $factors = [
            ['key' => 'R', 'name' => 'Ratings received', 'note' => $facts['ratings'] === 0
                ? 'No ratings yet — counted at the midpoint'
                : sprintf('%.1f ★ average from %d rating%s', $facts['average_stars'], $facts['ratings'], $facts['ratings'] === 1 ? '' : 's')],
            ['key' => 'V', 'name' => 'Completed transactions', 'note' => $facts['completed'] . ' completed · 10 points each, up to 100'],
            ['key' => 'L', 'name' => 'On-time returns', 'note' => $facts['on_time_percent'] . '% of your returns as a borrower were on time'],
            ['key' => 'T', 'name' => 'Time in the community', 'note' => $facts['months'] . ' month' . ($facts['months'] === 1 ? '' : 's') . ' since verification · full at 24'],
            ['key' => 'C', 'name' => 'Donations given', 'note' => $facts['donations'] . ' completed · 20 points each, up to 100'],
        ];

        $this->render('trust/index', [
            'score' => [
                'value' => (string) $result['score'],
                'badge' => $result['score'] >= 70 ? ['success', '✓', 'Trusted member'] : ['info', 'i', 'Current trust score'],
                'meta'  => 'Out of 100 · ' . $facts['completed'] . ' completed transactions · ' . ($member['division_name'] ?? ''),
            ],
            'factors' => array_map(static fn (array $factor): array => [
                'name'    => $factor['name'],
                'weight'  => (int) round(TrustScoreService::WEIGHTS[$factor['key']] * 100) . '%',
                'percent' => $result['factors'][$factor['key']],
                'note'    => $factor['note'],
            ], $factors),
            'penalties' => [
                'Upheld damage claims (12 months)' => $facts['upheld_claims'] . ' × −' . TrustScoreService::UPHELD_CLAIM_PENALTY,
                'Reserve shortfall covers (12 months)' => $facts['shortfalls'] . ' × −' . TrustScoreService::SHORTFALL_PENALTY,
                'Past suspensions' => $facts['suspensions'] . ' × −' . TrustScoreService::SUSPENSION_PENALTY,
            ],
            'blend' => sprintf(
                'Weighted %.0f, blended with the community midpoint over %d transaction%s to %.0f, minus %d in penalties.',
                $result['weighted'],
                $facts['completed'],
                $facts['completed'] === 1 ? '' : 's',
                $result['blended'],
                $result['penalty']
            ),
        ]);
    }
}
