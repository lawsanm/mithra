<?php

declare(strict_types=1);

/** Admin management of item categories; existing listing references are kept. */
final class CategoryService
{
    public function __construct(private ItemCategory $categories)
    {
    }

    /** @throws ValidationException */
    public function create(string $name, bool $active): int
    {
        $this->assertNameAvailable($name);

        return $this->categories->createCategory($name, $active);
    }

    /** @throws RecordNotFoundException|ValidationException */
    public function save(int $id, string $name, bool $active): int
    {
        if ($id === 0) {
            return $this->create($name, $active);
        }

        $this->update($id, $name, $active);

        return $id;
    }

    /** @throws RecordNotFoundException|ValidationException */
    public function update(int $id, string $name, bool $active): void
    {
        if ($this->categories->find($id) === null) {
            throw new RecordNotFoundException('No such category.');
        }

        $this->assertNameAvailable($name, $id);
        $this->categories->updateCategory($id, $name, $active);
    }

    /** @throws ValidationException */
    private function assertNameAvailable(string $name, int $exceptId = 0): void
    {
        if ($this->categories->nameTaken($name, $exceptId)) {
            throw ValidationException::field('name', 'A category with this name already exists.');
        }
    }
}
