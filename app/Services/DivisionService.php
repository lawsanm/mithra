<?php

declare(strict_types=1);

/**
 * The Admin's division CRUD (Plan §16.4): create, rename and archive a GN
 * division. Archiving is the delete — members, listings and bookings keep
 * pointing at the row, so it is never removed.
 *
 * The controller checks that each field is present and short enough; the rules
 * here are the ones that need the database.
 */
final class DivisionService
{
    public function __construct(private GnDivision $divisions)
    {
    }

    /**
     * @throws ValidationException when the name is already used in the district
     */
    public function create(string $name, string $district): int
    {
        $this->assertNameFree($name, $district);

        return $this->divisions->create($name, $district);
    }

    /**
     * @throws RecordNotFoundException|ValidationException
     */
    public function update(int $id, string $name, string $district): void
    {
        $this->existing($id);
        $this->assertNameFree($name, $district, $id);

        $this->divisions->updateDetails($id, $name, $district);
    }

    /**
     * @return string the archived division's name
     *
     * @throws RecordNotFoundException|ValidationException
     */
    public function archive(int $id): string
    {
        $division = $this->existing($id);

        if ($division['status'] === 'archived') {
            throw ValidationException::field('status', $division['name'] . ' is already archived.');
        }

        $this->divisions->archive($id);

        return (string) $division['name'];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RecordNotFoundException
     */
    private function existing(int $id): array
    {
        return $this->divisions->find($id) ?? throw new RecordNotFoundException('No such division.');
    }

    /**
     * @throws ValidationException
     */
    private function assertNameFree(string $name, string $district, int $exceptId = 0): void
    {
        if ($this->divisions->nameTaken($name, $district, $exceptId)) {
            throw ValidationException::field('name', sprintf('%s already has a division called %s.', $district, $name));
        }
    }
}
