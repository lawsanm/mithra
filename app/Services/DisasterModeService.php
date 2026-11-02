<?php

declare(strict_types=1);

/**
 * Admin lifecycle rules for Disaster Mode (Proposal §14).
 * Activation and its event history and alerts succeed or fail together.
 */
final class DisasterModeService
{
    public const TYPES = [
        'flooding' => 'Flooding',
        'fire' => 'Fire',
        'storm' => 'Storm / cyclone',
        'earthquake' => 'Earthquake',
        'other' => 'Other',
    ];

    public function __construct(
        private PDO $pdo,
        private GnDivision $divisions,
        private DisasterEvent $events,
        private User $users,
        private Notification $notifications
    ) {
    }

    /**
     * Activate one division and notify the affected platform roles.
     *
     * @return array{event_id: int, division_name: string}
     *
     * @throws RecordNotFoundException|ValidationException
     */
    public function activate(int $divisionId, int $adminId, string $type, string $endDate, string $notes): array
    {
        if (!isset(self::TYPES[$type])) {
            throw ValidationException::field('type', 'Choose a valid disaster type.');
        }

        if (!Validator::isDate($endDate) || $endDate < date('Y-m-d')) {
            throw ValidationException::field('end_date', 'The planned end date must be today or a future date.');
        }

        $notes = trim($notes);
        $reason = self::TYPES[$type] . ($notes === '' ? '' : ' — ' . $notes);

        if (mb_strlen($reason) > 255) {
            throw ValidationException::field('notes', 'The disaster details are too long.');
        }

        return Database::transaction($this->pdo, function () use ($divisionId, $adminId, $reason, $endDate): array {
            $division = $this->divisions->lockForDisasterMode($divisionId)
                ?? throw new RecordNotFoundException('No such division.');

            if ($division['status'] === 'archived') {
                throw ValidationException::field('division_id', 'Cannot activate Disaster Mode for an archived division.');
            }

            if ($division['disaster_mode_active'] || $this->events->activeForDivision($divisionId) !== null) {
                throw ValidationException::field('division_id', 'Disaster Mode is already active for this division.');
            }

            $until = $endDate . ' 23:59:59';
            $this->divisions->setDisasterMode($divisionId, true, $until);
            $eventId = $this->events->create($divisionId, $adminId, $reason, $until);

            $this->notifyActors(
                $eventId,
                'disaster_alert',
                'Disaster Mode activated: ' . $division['name'],
                $reason . ' · planned end ' . date('j M Y', strtotime($until)),
                'alert'
            );

            return ['event_id' => $eventId, 'division_name' => $division['name']];
        });
    }

    /**
     * End a division's active disaster event and notify affected roles.
     *
     * @throws RecordNotFoundException|ValidationException
     */
    public function deactivate(int $divisionId): string
    {
        return Database::transaction($this->pdo, function () use ($divisionId): string {
            $division = $this->divisions->lockForDisasterMode($divisionId)
                ?? throw new RecordNotFoundException('No such division.');
            $activeEvent = $this->events->activeForDivision($divisionId);

            if (!$division['disaster_mode_active'] && $activeEvent === null) {
                throw ValidationException::field('division_id', 'Disaster Mode is already inactive for this division.');
            }

            $this->divisions->setDisasterMode($divisionId, false);
            $this->events->endActiveForDivision($divisionId);

            $this->notifyActors(
                $activeEvent === null ? null : (int) $activeEvent['id'],
                'disaster_resolved',
                'Disaster Mode ended: ' . $division['name'],
                'The emergency in ' . $division['name'] . ' has been resolved.',
                'check'
            );

            return $division['name'];
        });
    }

    /**
     * End events whose planned end date has arrived. Safe to run repeatedly.
     */
    public function expirePlannedEvents(): string
    {
        $expired = 0;

        foreach ($this->events->dueForExpiry() as $candidate) {
            $didExpire = Database::transaction($this->pdo, function () use ($candidate): bool {
                $divisionId = $candidate['gn_division_id'];
                $division = $this->divisions->lockForDisasterMode($divisionId);

                if ($division === null) {
                    return false;
                }

                $event = $this->events->lockDueEvent($candidate['id'], $divisionId);

                if ($event === null || !$this->events->endIfDue($candidate['id'])) {
                    return false;
                }

                $remainingEvent = $this->events->activeForDivision($divisionId);
                $this->divisions->setDisasterMode(
                    $divisionId,
                    $remainingEvent !== null,
                    $remainingEvent['planned_end_at'] ?? null
                );

                if ($remainingEvent === null) {
                    $this->notifyActors(
                        (int) $event['id'],
                        'disaster_resolved',
                        'Disaster Mode ended: ' . $division['name'],
                        'The planned end date for this emergency has arrived.',
                        'check'
                    );
                }

                return true;
            });

            if ($didExpire) {
                $expired++;
            }
        }

        return $expired . ' disaster event(s) ended.';
    }

    private function notifyActors(?int $eventId, string $type, string $title, string $detail, string $icon): void
    {
        $payload = ['title' => $title, 'detail' => $detail, 'icon' => $icon];
        $sponsorPath = $eventId === null ? '/sponsor/notifications' : '/sponsor/disasters/' . $eventId;
        $liaisonPath = $eventId === null
            ? '/sponsor-liaison/dashboard'
            : '/sponsor-liaison/disasters/connection?event=' . $eventId;

        foreach ($this->users->idsInRole('sponsor') as $userId) {
            $this->notifications->push($userId, $type, $payload + ['href' => $sponsorPath]);
        }

        foreach ($this->users->idsInRole('sponsor_liaison') as $userId) {
            $this->notifications->push(
                $userId,
                $type,
                $payload + ['href' => $liaisonPath]
            );
        }
    }
}
