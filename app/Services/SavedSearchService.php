<?php

declare(strict_types=1);

/**
 * Saved searches (Plan 2.3): a member names the Browse filters they use often
 * and opens them again with one click.
 *
 * Only the filters Browse understands are kept, so a stored search can never
 * carry anything else back into a URL.
 */
final class SavedSearchService
{
    public const NAME_MAX   = 80;
    public const PER_MEMBER = 10;

    /** The Browse query parameters a saved search may carry. */
    public const FILTERS = ['q', 'category', 'type', 'community'];

    private const QUERY_MAX = 100;

    public function __construct(private SavedSearch $searches)
    {
    }

    /**
     * Keep the known filters, as non-empty trimmed strings, and drop the rest.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, string>
     */
    public static function cleanFilters(array $input): array
    {
        $clean = [];

        foreach (self::FILTERS as $key) {
            $value = $input[$key] ?? '';

            if (!is_scalar($value)) {
                continue;
            }

            $value = mb_substr(trim((string) $value), 0, self::QUERY_MAX);

            $valid = match ($key) {
                'type'      => in_array($value, ['rentals', 'donations'], true),
                'community' => $value === 'temporary',
                'category'  => preg_match('/^[a-z0-9-]+$/', $value) === 1,
                default     => $value !== '',
            };

            if ($valid) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * Why a new search cannot be saved; empty when it can.
     *
     * @return array<string, string>
     */
    public static function saveErrors(string $name, int $alreadySaved): array
    {
        if ($alreadySaved >= self::PER_MEMBER) {
            return ['name' => sprintf('You can keep %d saved searches. Delete one first.', self::PER_MEMBER)];
        }

        return self::nameErrors($name);
    }

    /**
     * @return array<string, string>
     */
    public static function nameErrors(string $name): array
    {
        if ($name === '') {
            return ['name' => 'Give the search a name.'];
        }

        if (mb_strlen($name) > self::NAME_MAX) {
            return ['name' => sprintf('Keep the name to %d characters.', self::NAME_MAX)];
        }

        return [];
    }

    /**
     * The member's searches, each with the Browse query it rebuilds.
     *
     * @return list<array{id: int, name: string, query: string}>
     */
    public function forMember(int $userId): array
    {
        return array_map(static function (array $row): array {
            $filters = json_decode((string) $row['filters'], true);

            return [
                'id'    => (int) $row['id'],
                'name'  => (string) $row['name'],
                'query' => http_build_query(self::cleanFilters(is_array($filters) ? $filters : [])),
            ];
        }, $this->searches->forMember($userId));
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @throws ValidationException
     */
    public function save(int $userId, string $name, array $filters): int
    {
        $name   = trim($name);
        $errors = self::saveErrors($name, $this->searches->countForMember($userId));

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->searches->create($userId, $name, self::cleanFilters($filters));
    }

    /**
     * @throws ValidationException|RecordNotFoundException
     */
    public function rename(int $id, int $userId, string $name): void
    {
        $this->ownedOrFail($id, $userId);
        $name   = trim($name);
        $errors = self::nameErrors($name);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->searches->rename($id, $userId, $name);
    }

    /**
     * @throws RecordNotFoundException
     */
    public function delete(int $id, int $userId): void
    {
        $this->ownedOrFail($id, $userId);
        $this->searches->delete($id, $userId);
    }

    /**
     * Someone else's search and a missing one look the same: not found.
     *
     * @throws RecordNotFoundException
     */
    private function ownedOrFail(int $id, int $userId): void
    {
        if ($this->searches->findOwned($id, $userId) === null) {
            throw new RecordNotFoundException('No such saved search.');
        }
    }
}
