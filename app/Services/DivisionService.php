<?php

declare(strict_types=1);

/**
 * The Admin's division CRUD (Plan §16.4): create, edit and archive a GN
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
     * @throws ValidationException when the district is not in the province, or
     *                             the name is already used in the district
     */
    public function create(string $province, string $district, string $name, string $postalCode): int
    {
        $this->assertDistrictInProvince($province, $district);
        $this->assertNameFree($name, $district);

        return $this->divisions->create($province, $district, $name, $postalCode);
    }

    /**
     * @throws RecordNotFoundException|ValidationException
     */
    public function update(int $id, string $province, string $district, string $name, string $postalCode): void
    {
        $this->existing($id);
        $this->assertDistrictInProvince($province, $district);
        $this->assertNameFree($name, $district, $id);

        $this->divisions->updateDetails($id, $province, $district, $name, $postalCode);
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
     * The district dropdown only offers the chosen province's districts, but a
     * crafted request could still pair them wrongly.
     *
     * @throws ValidationException
     */
    private function assertDistrictInProvince(string $province, string $district): void
    {
        if (!in_array($district, Province::districtsOf($province), true)) {
            throw ValidationException::field('district', sprintf('%s is not in %s.', $district, $province));
        }
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
