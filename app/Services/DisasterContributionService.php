<?php

declare(strict_types=1);

/**
 * Off-platform Disaster Mode contributions (Proposal §14.3). The service
 * records evidence and confirmations only; it never moves points.
 */
final class DisasterContributionService
{
    public const STATUSES = [
        'awaiting' => 'Awaiting Moderator',
        'ready' => 'Ready to verify',
        'queried' => 'Queried',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
    ];

    public const KINDS = ['cash', 'goods'];
    public const OUTCOMES = ['received', 'partial', 'none'];

    public function __construct(
        private PDO $pdo,
        private DisasterContribution $contributions,
        private DisasterEvent $events,
        private Sponsor $sponsors,
        private GnDivision $divisions,
        private User $users,
        private Notification $notifications
    ) {
    }

    /** @param array<string, mixed> $input */
    public function record(int $liaisonId, array $input): int
    {
        $record = $this->validatedRecord($input);

        return Database::transaction($this->pdo, function () use ($liaisonId, $record): int {
            $event = $this->events->findWithDivision($record['event']);
            if ($event === null) {
                throw ValidationException::field('disaster_event_id', 'Choose an existing disaster event.');
            }
            if (empty($event['moderator_id'])) {
                throw ValidationException::field('disaster_event_id', 'This division has no Moderator to confirm the contribution.');
            }
            $this->assertDateWithinEvent($record['handed_over'], $event['started_at']);
            if ($this->sponsors->find($record['sponsor']) === null) {
                throw ValidationException::field('sponsor_id', 'Choose an existing sponsor.');
            }

            $moderatorId = (int) $event['moderator_id'];
            $id = $this->contributions->createRecord($record + ['recorder' => $liaisonId]);
            $this->contributions->recordEvent($id, $liaisonId, 'recorded');
            $this->notifications->push($moderatorId, 'disaster_contribution_recorded', [
                'title' => 'Sponsor contribution needs confirmation',
                'detail' => $record['description'] . ' · ' . $event['division_name'],
                'icon' => 'alert',
                'href' => '/moderator/disasters/contributions',
            ]);

            return $id;
        });
    }

    /** @param array<string, mixed> $input */
    public function update(int $liaisonId, int $id, array $input): void
    {
        $record = $this->validatedRecord($input, false);

        Database::transaction($this->pdo, function () use ($liaisonId, $id, $record): void {
            $current = $this->contributions->lock($id)
                ?? throw new RecordNotFoundException('No such disaster contribution.');
            if (in_array($current['status'], ['verified', 'rejected'], true)) {
                throw ValidationException::field('record', 'Verified or rejected contributions are locked.');
            }
            $event = $this->events->findWithDivision($record['event']);
            if ($event === null || empty($event['moderator_id'])) {
                throw ValidationException::field('disaster_event_id', 'Choose an existing disaster event with a Moderator.');
            }
            $this->assertDateWithinEvent($record['handed_over'], $event['started_at']);
            if ($this->sponsors->find($record['sponsor']) === null) {
                throw ValidationException::field('sponsor_id', 'Choose an existing sponsor.');
            }

            $snapshot = $this->contributions->findWithEvent($id)
                ?? throw new RecordNotFoundException('No such disaster contribution.');
            $currentValue = $current['estimated_value'] === null ? null : (int) $current['estimated_value'];
            $reset = (int) $current['disaster_event_id'] !== $record['event']
                || (int) $current['sponsor_id'] !== $record['sponsor']
                || $currentValue !== $record['value']
                || (string) $current['contribution_kind'] !== $record['kind']
                || (string) ($current['handed_over_on'] ?? '') !== $record['handed_over']
                || (string) $snapshot['description'] !== $record['description'];
            if ($record['proof'] === null) {
                $existingProof = PhotoStore::paths($snapshot['sponsor_proof'] ?? null);
                $record['proof'] = $existingProof === [] ? null : json_encode($existingProof, JSON_THROW_ON_ERROR);
            }

            $this->contributions->updateRecord($id, $record + [
                'status' => $current['status'],
                'received_description' => $snapshot['received_description'] ?? null,
                'received_value' => $snapshot['received_value'] ?? null,
                'received_on' => $snapshot['received_on'] ?? null,
                'ack_reference' => $snapshot['ack_reference'] ?? null,
                'confirmed_at' => $snapshot['confirmed_at'] ?? null,
            ], $reset);
            $this->contributions->recordEvent($id, $liaisonId, $reset ? 'edited_confirmation_reset' : 'edited');

            if ($reset) {
                $this->notifications->push((int) $event['moderator_id'], 'disaster_contribution_updated', [
                    'title' => 'Contribution details changed; please confirm again',
                    'detail' => $record['description'] . ' · ' . $event['division_name'],
                    'icon' => 'alert',
                    'href' => '/moderator/disasters/contributions',
                ]);
            }
        });
    }

    /** @param array<string, mixed> $input */
    public function confirm(int $moderatorId, int $id, array $input): void
    {
        $outcome = $this->text($input, 'outcome');
        if (!in_array($outcome, self::OUTCOMES, true)) {
            throw ValidationException::field('outcome', 'Choose what you received.');
        }

        $description = $this->text($input, 'received_description');
        $receivedValue = $this->optionalInteger($input, 'received_value');
        $receivedOn = $this->text($input, 'received_on');
        $ackReference = $this->text($input, 'ack_reference');
        $note = trim($this->text($input, 'note'));
        $proof = $this->paths($input['proof_files'] ?? []);
        $reliefIds = $this->integerList($input['relief_record_ids'] ?? []);

        if ($outcome !== 'none' && $description === '') {
            throw ValidationException::field('received_description', 'Describe what arrived.');
        }
        if (mb_strlen($description) > 500) {
            throw ValidationException::field('received_description', 'The received description must be 500 characters or fewer.');
        }
        if ($outcome !== 'none' && !Validator::isDate($receivedOn)) {
            throw ValidationException::field('received_on', 'Enter a valid date received.');
        }
        if ($outcome !== 'none' && $ackReference === '') {
            throw ValidationException::field('ack_reference', 'Enter the acknowledgement number you gave the sponsor.');
        }
        if ($outcome !== 'received' && $note === '') {
            throw ValidationException::field('note', 'Explain what differed or why nothing was received.');
        }
        if ($outcome !== 'none' && $proof === []) {
            throw ValidationException::field('proof_files', 'Upload the signed acknowledgement or a photo of what arrived.');
        }
        if (mb_strlen($ackReference) > 100 || mb_strlen($note) > 2000) {
            throw ValidationException::field('note', 'The acknowledgement number or note is too long.');
        }

        Database::transaction($this->pdo, function () use (
            $moderatorId, $id, $outcome, $description, $receivedValue, $receivedOn,
            $ackReference, $note, $proof, $reliefIds
        ): void {
            $current = $this->contributions->lock($id)
                ?? throw new RecordNotFoundException('No such disaster contribution.');
            if (!in_array($current['status'], ['awaiting', 'queried'], true)) {
                throw ValidationException::field('record', 'This contribution is not waiting for Moderator confirmation.');
            }

            $record = $this->contributions->findWithEvent($id)
                ?? throw new RecordNotFoundException('No such disaster contribution.');
            $divisionId = $this->divisions->moderatedByOrFail($moderatorId);
            if ((int) $record['gn_division_id'] !== $divisionId
                || (int) ($record['moderator_id'] ?? 0) !== $moderatorId) {
                throw new RecordNotFoundException('No such disaster contribution.');
            }
            $division = $this->divisions->lockForDisasterMode($divisionId);
            if ($division === null || $division['moderator_id'] !== $moderatorId) {
                throw new RecordNotFoundException('No such disaster contribution.');
            }
            if ($outcome !== 'none' && $record['contribution_kind'] === 'cash' && $receivedValue === null) {
                throw ValidationException::field('received_value', 'Enter the cash amount you received.');
            }
            if ($outcome !== 'none') {
                $this->assertDateWithinEvent($receivedOn, (string) $record['started_at'], 'received_on');
                if ($receivedOn > date('Y-m-d')) {
                    throw ValidationException::field('received_on', 'The received date cannot be in the future.');
                }
            }

            foreach ($reliefIds as $reliefId) {
                if (!$this->contributions->reliefBelongsToEvent($reliefId, (int) $record['disaster_event_id'], $moderatorId)) {
                    throw ValidationException::field('relief_record_ids', 'Choose only relief records from this disaster that you recorded.');
                }
            }

            $this->contributions->addConfirmation($id, [
                'moderator' => $moderatorId,
                'outcome' => $outcome,
                'description' => $outcome === 'none' ? null : $description,
                'value' => $outcome === 'none' ? null : $receivedValue,
                'received_on' => $outcome === 'none' ? null : $receivedOn,
                'ack_reference' => $outcome === 'none' ? null : $ackReference,
                'proof' => $proof === [] ? null : json_encode($proof, JSON_THROW_ON_ERROR),
                'note' => $note === '' ? null : $note,
            ]);
            $this->contributions->replaceReliefLinks($id, $moderatorId, $reliefIds);
            $this->contributions->recordEvent($id, $moderatorId, 'moderator_confirmed', $note === '' ? null : $note);

            foreach ($this->users->idsInRole('sponsor_liaison') as $liaisonId) {
                $this->notifications->push($liaisonId, 'disaster_contribution_confirmed', [
                    'title' => 'Moderator confirmed a disaster contribution',
                    'detail' => $record['company_name'] . ' · ' . $record['division_name'],
                    'icon' => 'check',
                    'href' => '/sponsor-liaison/disasters/contributions/' . $id,
                ]);
            }
        });
    }

    /** @param array<string, mixed> $input */
    public function decide(int $liaisonId, int $id, array $input): void
    {
        $decision = $this->text($input, 'decision');
        $note = trim($this->text($input, 'note'));
        $value = $this->optionalInteger($input, 'verified_value');
        if (!in_array($decision, ['verify', 'query_sponsor', 'query_moderator', 'reject'], true)) {
            throw ValidationException::field('decision', 'Choose a valid contribution decision.');
        }
        if ($decision !== 'verify' && $note === '') {
            throw ValidationException::field('note', 'Add a note explaining this decision.');
        }
        if (mb_strlen($note) > 2000) {
            throw ValidationException::field('note', 'The note must be 2,000 characters or fewer.');
        }
        if ($decision === 'verify' && $value === null) {
            throw ValidationException::field('verified_value', 'Enter the value to include in CSR reporting.');
        }

        Database::transaction($this->pdo, function () use ($liaisonId, $id, $decision, $note, $value): void {
            $current = $this->contributions->lock($id)
                ?? throw new RecordNotFoundException('No such disaster contribution.');
            if ($current['status'] !== 'ready') {
                throw ValidationException::field('record', 'Only a Moderator-confirmed contribution is ready for Liaison review.');
            }

            $record = $this->contributions->findWithEvent($id)
                ?? throw new RecordNotFoundException('No such disaster contribution.');
            $confirmation = $this->contributions->latestConfirmation($id)
                ?? throw ValidationException::field('record', 'A Moderator confirmation is required before this decision.');
            if ($decision === 'verify' && $confirmation['outcome'] === 'none') {
                throw ValidationException::field('decision', 'A contribution marked not received cannot be verified. Reject or query it.');
            }
            if ($decision === 'query_moderator' && empty($record['moderator_id'])) {
                throw ValidationException::field('decision', 'There is no current Moderator to receive this query.');
            }

            $status = match ($decision) {
                'verify' => 'verified',
                'reject' => 'rejected',
                default => 'queried',
            };
            $this->contributions->applyDecision($id, [
                'status' => $status,
                'notes' => $note === '' ? $record['notes'] : $note,
                'verified_value' => $decision === 'verify' ? $value : null,
                'verified_by' => $decision === 'verify' ? $liaisonId : null,
                'verified_at' => $decision === 'verify' ? date('Y-m-d H:i:s') : null,
            ]);
            $this->contributions->recordEvent($id, $liaisonId, $decision, $note === '' ? null : $note);

            if ($decision === 'query_moderator') {
                $this->notifications->push((int) $record['moderator_id'], 'disaster_contribution_queried', [
                    'title' => 'The Sponsor Liaison needs more information',
                    'detail' => $record['company_name'] . ' · ' . $note,
                    'icon' => 'info',
                    'href' => '/moderator/disasters/contributions/' . $id . '/confirm',
                ]);
            } elseif ($decision === 'query_sponsor' && !empty($record['sponsor_user_id'])) {
                $this->notifications->push((int) $record['sponsor_user_id'], 'disaster_contribution_queried', [
                    'title' => 'The Sponsor Liaison has a question about your contribution',
                    'detail' => $note,
                    'icon' => 'info',
                    'href' => '/sponsor/disasters/' . $record['disaster_event_id'],
                ]);
            } elseif ($decision === 'verify' && !empty($record['sponsor_user_id'])) {
                $this->notifications->push((int) $record['sponsor_user_id'], 'disaster_contribution_verified', [
                    'title' => 'Your disaster contribution was verified',
                    'detail' => $record['division_name'] . ' · LKR ' . number_format((int) $value),
                    'icon' => 'check',
                    'href' => '/sponsor/disasters/' . $record['disaster_event_id'],
                ]);
            }
        });
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validatedRecord(array $input, bool $requireProof = true): array
    {
        $event = $this->positiveInteger($input, 'disaster_event_id', 'Disaster event');
        $sponsor = $this->positiveInteger($input, 'sponsor_id', 'Sponsor');
        $kind = $this->text($input, 'contribution_kind');
        $description = trim($this->text($input, 'description'));
        $value = $this->optionalInteger($input, 'estimated_value');
        $handedOver = $this->text($input, 'handed_over_on');
        $receipt = trim($this->text($input, 'receipt_reference'));
        $notes = trim($this->text($input, 'notes'));
        $proof = $this->paths($input['sponsor_proof'] ?? []);

        if ($requireProof && $proof === []) {
            throw ValidationException::field('sponsor_proof', 'Upload the sponsor receipt or delivery proof.');
        }

        if (!in_array($kind, self::KINDS, true)) {
            throw ValidationException::field('contribution_kind', 'Choose cash or goods.');
        }
        if ($description === '' || mb_strlen($description) > 255 || !Validator::hasLetters($description)) {
            throw ValidationException::field('description', 'Enter a description up to 255 characters.');
        }
        if ($kind === 'cash' && $value === null) {
            throw ValidationException::field('estimated_value', 'Enter the cash value in LKR.');
        }
        if (!Validator::isDate($handedOver) || $handedOver > date('Y-m-d')) {
            throw ValidationException::field('handed_over_on', 'Enter a valid handover date.');
        }
        if (mb_strlen($receipt) > 100 || mb_strlen($notes) > 2000) {
            throw ValidationException::field('notes', 'The receipt reference or notes are too long.');
        }

        return [
            'event' => $event,
            'sponsor' => $sponsor,
            'kind' => $kind,
            'description' => $description,
            'value' => $value,
            'handed_over' => $handedOver,
            'receipt' => $receipt === '' ? null : $receipt,
            'proof' => $proof === [] ? null : json_encode($proof, JSON_THROW_ON_ERROR),
            'notes' => $notes === '' ? null : $notes,
        ];
    }

    /** @param array<string, mixed> $input */
    private function positiveInteger(array $input, string $field, string $label): int
    {
        $value = filter_var($input[$field] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw ValidationException::field($field, 'Choose a valid ' . strtolower($label) . '.');
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    private function optionalInteger(array $input, string $field): ?int
    {
        $raw = is_scalar($input[$field] ?? null) ? trim((string) $input[$field]) : '';
        if ($raw === '') {
            return null;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < 0 || $value > 4294967295) {
            throw ValidationException::field($field, 'Enter a valid non-negative whole number.');
        }

        return $value;
    }

    private function assertDateWithinEvent(string $date, string $startedAt, string $field = 'handed_over_on'): void
    {
        if ($date < substr($startedAt, 0, 10)) {
            throw ValidationException::field($field, 'The date cannot be before this disaster began.');
        }
    }

    /** @param array<string, mixed> $input */
    private function text(array $input, string $field): string
    {
        return is_string($input[$field] ?? null) ? trim($input[$field]) : '';
    }

    /** @return list<string> */
    private function paths(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        if (count($raw) > 3) {
            throw ValidationException::field('proof_files', 'Upload three proof files at most.');
        }
        $paths = [];
        foreach ($raw as $path) {
            if (!is_string($path) || preg_match('#^disaster-proofs/[a-f0-9]{32}\.jpg$#D', $path) !== 1) {
                throw ValidationException::field('proof_files', 'The proof upload is not valid.');
            }
            $paths[] = $path;
        }

        return array_values(array_unique($paths));
    }

    /** @return list<int> */
    private function integerList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                throw ValidationException::field('relief_record_ids', 'Choose valid relief records.');
            }
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }
}
