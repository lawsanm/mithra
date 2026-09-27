<?php

declare(strict_types=1);

/**
 * Sri Lanka's nine provinces and the 25 districts inside each. This is fixed
 * reference data rather than a table: it drives the province → district
 * dropdowns and checks that a division's district belongs to its province.
 */
final class Province
{
    /** @var array<string, list<string>> */
    private const DISTRICTS = [
        'Western Province'       => ['Colombo', 'Gampaha', 'Kalutara'],
        'Central Province'       => ['Kandy', 'Matale', 'Nuwara Eliya'],
        'Southern Province'      => ['Galle', 'Matara', 'Hambantota'],
        'Northern Province'      => ['Jaffna', 'Kilinochchi', 'Mannar', 'Mullaitivu', 'Vavuniya'],
        'Eastern Province'       => ['Ampara', 'Batticaloa', 'Trincomalee'],
        'North Western Province' => ['Kurunegala', 'Puttalam'],
        'North Central Province' => ['Anuradhapura', 'Polonnaruwa'],
        'Uva Province'           => ['Badulla', 'Monaragala'],
        'Sabaragamuwa Province'  => ['Kegalle', 'Ratnapura'],
    ];

    /** @return array<string, list<string>> province => its districts */
    public static function districtsByProvince(): array
    {
        return self::DISTRICTS;
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::DISTRICTS);
    }

    /** @return list<string> the districts of one province, empty if unknown */
    public static function districtsOf(string $province): array
    {
        return self::DISTRICTS[$province] ?? [];
    }
}
