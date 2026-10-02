<?php

declare(strict_types=1);

/**
 * Saved searches (Plan 2.3). They are listed on Browse; these actions change
 * them and send the member back there.
 */
final class SavedSearchController extends Controller
{
    private SavedSearchService $service;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->service = new SavedSearchService(new SavedSearch($pdo));
    }

    /**
     * POST /saved-searches — save the filters Browse is showing.
     */
    public function store(): void
    {
        $filters = SavedSearchService::cleanFilters($_POST);

        try {
            $this->service->save($this->userId(), $this->posted('name'), $filters);
            $this->flash('Search saved.');
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        }

        $this->redirect('/items/browse' . ($filters === [] ? '' : '?' . http_build_query($filters)));
    }

    /**
     * POST /saved-searches/{id} — rename.
     */
    public function update(int $id): void
    {
        $this->change(fn (): mixed => $this->service->rename($id, $this->userId(), $this->posted('name')), 'Search renamed.');
    }

    /**
     * POST /saved-searches/{id}/delete.
     */
    public function destroy(int $id): void
    {
        $this->change(fn (): mixed => $this->service->delete($id, $this->userId()), 'Saved search deleted.');
    }

    private function change(callable $action, string $message): void
    {
        $this->attempt(function () use ($action, $message): string {
            $action();

            return $message;
        }, '/items/browse#saved-searches', ['Saved search not found', 'Choose one of your saved searches on Browse.']);
    }
}
