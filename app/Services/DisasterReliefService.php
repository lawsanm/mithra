<?php

declare(strict_types=1);

/**
 * The moderator's disaster relief records (Plan §14.1 step 5, §16.3): during
 * Disaster Mode sponsors hand cash or goods to the division's Moderator, who
 * records here what was handed out, where, to how many households and from
 * which sponsor's help.
 *
 * The rules:
 *   - a moderator only sees and touches records in the division they moderate;
 *   - a record is added only while that division has an active disaster;
 *   - once the Admin ends the disaster its records are locked — no edit, no
 *     delete — so the CSR and transparency figures built on them stay put;
 *   - no points move: this is record-keeping only (§14).
 *
 * The controller checks that each field is present and short enough; the rules
 * here are the formats and the ones that need the database.
 */
final class DisasterReliefService
{
    /** Relief type => label, in the order the form offers them. */
    public const RELIEF_TYPES = [
        'food'     => 'Food and dry rations',
        'water'    => 'Drinking water',
        'shelter'  => 'Shelter and bedding',
        'medical'  => 'Medical supplies',
        'clothing' => 'Clothing',
        'cash'     => 'Cash assistance',
        'other'    => 'Other',
    ];

    public function __construct(
        private GnDivision $divisions,
        private DisasterEvent $events,
        private DisasterReliefRecord $records,
        private Sponsor $sponsors
    ) {
    }

    /**
     * The division this moderator looks after.
     *
     * @throws AccessDeniedException when they hold no appointment
     */
    public function divisionFor(int $moderatorId): int
    {
        return $this->divisions->moderatedBy($moderatorId)
            ?? throw new AccessDeniedException('This account does not moderate a division.');
    }

    /**
     * The division's active disaster, or null when Disaster Mode is off.
     *
     * @return array<string, mixed>|null
     *
     * @throws AccessDeniedException
     */
    public function activeDisaster(int $moderatorId): ?array
    {
        return $this->events->activeForDivision($this->divisionFor($moderatorId));
    }

    /**
     * @param array<string, string> $input validated form values
     *
     * @return int the new record's id
     *
     * @throws AccessDeniedException|ValidationException
     */
    public function create(int $moderatorId, array $input, string $today): int
    {
        $disaster = $this->activeDisaster($moderatorId)
            ?? throw ValidationException::field('disaster', 'Relief can only be recorded while Disaster Mode is active in your division.');

        $record = self::record($input, $today, substr((string) $disaster['started_at'], 0, 10));
        $this->assertSponsorActive($record['sponsor_id']);

        return $this->records->create((int) $disaster['id'], $moderatorId, $record);
    }

    /**
     * A record this moderator may still change.
     *
     * @return array<string, mixed>
     *
     * @throws AccessDeniedException|RecordNotFoundException|ValidationException
     */
    public function editable(int $moderatorId, int $id): array
    {
        $row = $this->records->findWithEvent($id);

        // Another division's record looks the same as a missing one (§8).
        if ($row === null || (int) $row['gn_division_id'] !== $this->divisionFor($moderatorId)) {
            throw new RecordNotFoundException('No such relief record.');
        }

        if ((int) $row['disaster_active'] !== 1) {
            throw ValidationException::field('record', 'This disaster has ended, so its relief records are locked for reporting.');
        }

        return $row;
    }

    /**
     * @param array<string, string> $input validated form values
     *
     * @throws AccessDeniedException|RecordNotFoundException|ValidationException
     */
    public function update(int $moderatorId, int $id, array $input, string $today): void
    {
        $row    = $this->editable($moderatorId, $id);
        $record = self::record($input, $today, substr((string) $row['disaster_started_at'], 0, 10));

        // A sponsor deactivated after the record was made may stay on it.
        if ($record['sponsor_id'] !== (int) ($row['sponsor_id'] ?? 0)) {
            $this->assertSponsorActive($record['sponsor_id']);
        }

        $this->records->updateRecord($id, $record);
    }

    /**
     * @return string the deleted record's description, for the confirmation
     *
     * @throws AccessDeniedException|RecordNotFoundException|ValidationException
     */
    public function delete(int $moderatorId, int $id): string
    {
        $row = $this->editable($moderatorId, $id);
        $this->records->delete($id);

        return (string) $row['description'];
    }

    /**
     * Every disaster in this moderator's division, newest first.
     *
     * @return list<array<string, mixed>>
     *
     * @throws AccessDeniedException
     */
    public function disasters(int $moderatorId): array
    {
        return $this->events->forDivision($this->divisionFor($moderatorId));
    }

    /**
     * The relief report for one disaster in this moderator's division: the
     * disaster, every record, and the totals. Available during and after the
     * disaster; it is read-only.
     *
     * @return array{disaster: array<string, mixed>, records: list<array<string, mixed>>,
     *               summary: array<string, mixed>}
     *
     * @throws AccessDeniedException|RecordNotFoundException
     */
    public function report(int $moderatorId, int $eventId): array
    {
        $disaster = $this->events->findWithDivision($eventId);

        // Another division's disaster looks the same as a missing one (§8).
        if ($disaster === null || (int) $disaster['gn_division_id'] !== $this->divisionFor($moderatorId)) {
            throw new RecordNotFoundException('No such disaster.');
        }

        $records = $this->records->allForEvent($eventId);

        return ['disaster' => $disaster, 'records' => $records, 'summary' => self::summarise($records)];
    }

    /**
     * Totals for a relief report, overall and grouped by relief type and by
     * sponsor, each group largest first. Needs no database.
     *
     * @param list<array<string, mixed>> $records rows with relief_type, households_reached,
     *                                            estimated_value, sponsor_id, sponsor_name
     *
     * @return array{records: int, households: int, value: int, sponsors: int,
     *               by_type: list<array{label: string, records: int, households: int, value: int}>,
     *               by_sponsor: list<array{label: string, records: int, households: int, value: int}>}
     */
    public static function summarise(array $records): array
    {
        $byType    = [];
        $bySponsor = [];
        $totals    = ['records' => 0, 'households' => 0, 'value' => 0];

        foreach ($records as $row) {
            $households = (int) $row['households_reached'];
            $value      = (int) ($row['estimated_value'] ?? 0);
            $type       = self::RELIEF_TYPES[(string) $row['relief_type']] ?? 'Other';
            $sponsorKey = $row['sponsor_id'] === null ? 0 : (int) $row['sponsor_id'];
            $sponsor    = $sponsorKey === 0 ? 'Not from a sponsor' : (string) $row['sponsor_name'];

            self::addTo($byType, $type, $type, $households, $value);
            self::addTo($bySponsor, $sponsorKey, $sponsor, $households, $value);

            $totals['records']++;
            $totals['households'] += $households;
            $totals['value']      += $value;
        }

        $largestFirst = static fn (array $a, array $b): int => [$b['households'], $b['value']] <=> [$a['households'], $a['value']];
        usort($byType, $largestFirst);
        usort($bySponsor, $largestFirst);

        return $totals + [
            'sponsors'   => count(array_filter(array_keys($bySponsor), static fn (int $key): bool => $key !== 0)),
            'by_type'    => array_values($byType),
            'by_sponsor' => array_values($bySponsor),
        ];
    }

    /**
     * The row to store from a submitted form: optional fields left empty
     * become NULL, and the date and type are checked. Needs no database.
     *
     * @param array<string, string> $input
     * @param string                $today         Y-m-d
     * @param string                $disasterStart Y-m-d, the first day relief can date from
     *
     * @return array{sponsor_id: ?int, relief_type: string, description: string, location: string,
     *               households_reached: int, estimated_value: ?int, distributed_on: string,
     *               notes: ?string}
     *
     * @throws ValidationException
     */
    public static function record(array $input, string $today, string $disasterStart): array
    {
        $optional = static function (string $field) use ($input): ?string {
            $value = trim((string) ($input[$field] ?? ''));

            return $value === '' ? null : $value;
        };

        $sponsor = $optional('sponsor_id');
        $value   = $optional('estimated_value');

        $record = [
            'sponsor_id'         => $sponsor === null ? null : (int) $sponsor,
            'relief_type'        => (string) ($input['relief_type'] ?? ''),
            'description'        => trim((string) ($input['description'] ?? '')),
            'location'           => trim((string) ($input['location'] ?? '')),
            'households_reached' => (int) ($input['households_reached'] ?? 0),
            'estimated_value'    => $value === null ? null : (int) $value,
            'distributed_on'     => trim((string) ($input['distributed_on'] ?? '')),
            'notes'              => $optional('notes'),
        ];

        $errors = [];

        if (!array_key_exists($record['relief_type'], self::RELIEF_TYPES)) {
            $errors['relief_type'] = 'Choose a valid relief type.';
        }

        if ($record['description'] === '') {
            $errors['description'] = 'What was given is required.';
        } elseif (preg_match('/\p{L}/u', $record['description']) !== 1) {
            // "40" alone says nothing about what was handed out; a letter in any
            // script (Sinhala and Tamil included) means it was described in words.
            $errors['description'] = 'Describe what was given in words, not only numbers.';
        }

        if ($record['location'] === '') {
            $errors['location'] = 'Location is required.';
        }

        if ($record['households_reached'] < 1) {
            $errors['households_reached'] = 'Households reached must be at least 1.';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $record['distributed_on']);

        if ($date === false || $date->format('Y-m-d') !== $record['distributed_on']) {
            $errors['distributed_on'] = 'Enter the date the relief was handed out.';
        } elseif ($record['distributed_on'] > $today) {
            $errors['distributed_on'] = 'The date cannot be in the future.';
        } elseif ($record['distributed_on'] < $disasterStart) {
            $errors['distributed_on'] = 'The date cannot be before the disaster began.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $record;
    }

    /**
     * Count one record into its group of a report breakdown.
     *
     * @param array<int|string, array{label: string, records: int, households: int, value: int}> $groups
     */
    private static function addTo(array &$groups, int|string $key, string $label, int $households, int $value): void
    {
        $groups[$key] ??= ['label' => $label, 'records' => 0, 'households' => 0, 'value' => 0];
        $groups[$key]['records']++;
        $groups[$key]['households'] += $households;
        $groups[$key]['value']      += $value;
    }

    /**
     * @throws ValidationException
     */
    private function assertSponsorActive(?int $sponsorId): void
    {
        if ($sponsorId !== null && !$this->sponsors->isActive($sponsorId)) {
            throw ValidationException::field('sponsor_id', 'Choose an active sponsor, or leave it empty.');
        }
    }
}
