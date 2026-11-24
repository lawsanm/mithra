<?php

declare(strict_types=1);

/** Moderator-to-Admin handoff for a newly reported division emergency. */
final class DisasterReportService
{
    public function __construct(
        private PDO $pdo,
        private GnDivision $divisions,
        private User $users,
        private Notification $notifications
    ) {
    }

    /**
     * @throws AccessDeniedException|RecordNotFoundException|ValidationException
     */
    public function report(int $moderatorId, string $type, string $details): void
    {
        if (!isset(DisasterModeService::TYPES[$type])) {
            throw ValidationException::field('type', 'Choose a valid disaster type.');
        }

        $details = trim($details);
        if ($details === '' || mb_strlen($details) > 500 || !Validator::hasLetters($details)) {
            throw ValidationException::field('details', 'Enter incident details in 1–500 characters.');
        }

        $divisionId = $this->divisions->moderatedByOrFail($moderatorId);
        $adminIds = $this->users->idsInRole('admin');
        if ($adminIds === []) {
            throw ValidationException::field('report', 'No active Admin account is available to receive the report.');
        }

        Database::transaction($this->pdo, function () use ($divisionId, $moderatorId, $type, $details, $adminIds): void {
            $division = $this->divisions->lockForDisasterMode($divisionId)
                ?? throw new RecordNotFoundException('No such division.');

            if ($division['moderator_id'] !== $moderatorId) {
                throw new AccessDeniedException('The moderator assignment changed.');
            }
            if ($division['disaster_mode_active']) {
                throw ValidationException::field('report', 'Disaster Mode is already active in your division.');
            }

            $moderator = $this->users->find($moderatorId);
            $moderatorName = (string) ($moderator['full_name'] ?? 'Moderator');
            $payload = [
                'title' => 'Disaster reported: ' . $division['name'],
                'detail' => DisasterModeService::TYPES[$type] . ' · ' . $details . ' · Reported by ' . $moderatorName,
                'division_id' => $divisionId,
                'disaster_type' => $type,
                'icon' => 'alert',
                'href' => '/admin/disaster',
            ];

            foreach ($adminIds as $adminId) {
                $this->notifications->push($adminId, 'disaster_report', $payload);
            }
        });
    }
}
