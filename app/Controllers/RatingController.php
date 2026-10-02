<?php

declare(strict_types=1);

/**
 * Ratings and reviews (Plan 3.5) — what the member gave and received, what is
 * waiting for their rating, and the rate / edit / remove actions. The rules
 * live in RatingService.
 */
final class RatingController extends Controller
{
    private Rating $ratings;
    private RatingService $service;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->ratings = new Rating($pdo);
        $this->service = new RatingService(
            $this->ratings,
            new Booking($pdo),
            new Donation($pdo),
            new Gift($pdo),
            TrustController::service($pdo)
        );
    }

    /**
     * GET /ratings — ?box=given|received, plus ?rate=booking-12 or ?edit=5 to
     * open the rating dialog for one record.
     */
    public function index(): void
    {
        $me   = $this->userId();
        $box  = ($_GET['box'] ?? '') === 'given' ? 'given' : 'received';
        $now  = new DateTimeImmutable();

        $tabs = [];
        foreach (['received', 'given'] as $direction) {
            $tabs[] = [
                'label'  => ucfirst($direction) . ' (' . $this->ratings->countForMember($me, $direction) . ')',
                'box'    => $direction,
                'active' => $box === $direction,
            ];
        }

        $waiting = array_map(static fn (array $row): array => [
            'name'  => (string) $row['ratee_name'],
            'title' => (string) $row['title'],
            'meta'  => ($row['kind'] === 'donation' ? 'Donation' : ($row['status'] === 'completed' ? 'Rental' : 'Cancelled booking'))
                . ' · ' . date('j M Y', strtotime((string) $row['ended_at'])),
            'href'  => base_url() . '/ratings?rate=' . $row['kind'] . '-' . $row['record_id'] . '#rate-review',
        ], $this->ratings->waitingFor($me));

        [$rateForm, $rateOpen] = $this->dialog($me);

        $this->render('ratings/index', [
            'tabs'     => $tabs,
            'box'      => $box,
            'waiting'  => $waiting,
            'reviews'  => array_map(static fn (array $row): array => [
                'id'       => (int) $row['id'],
                'initials' => User::initials((string) $row['counterparty']),
                'author'   => (string) $row['counterparty'],
                'rating'   => (int) $row['stars'],
                'text'     => (string) $row['comment'],
                'meta'     => date('j M Y', strtotime((string) $row['created_at'])) . ' · ' . ($row['item_title'] ?? 'Review'),
                'editable' => $box === 'given' && RatingService::stillEditable((string) $row['created_at'], $now),
            ], $this->ratings->forMember($me, $box)),
            'rateForm' => $rateForm,
            'rateOpen' => $rateOpen,
        ]);
    }

    /**
     * POST /ratings — rate a booking, donation or gift.
     */
    public function store(): void
    {
        try {
            $this->service->rate(
                $this->userId(),
                (string) ($_POST['kind'] ?? ''),
                (int) ($_POST['record_id'] ?? 0),
                (int) ($_POST['rating'] ?? 0),
                (string) ($_POST['review'] ?? ''),
                $this->postedTags()
            );
            $this->flash('Thank you — your rating is saved.');
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        }

        $this->redirect('/ratings?box=given');
    }

    /**
     * POST /ratings/{id} — change my rating within 7 days.
     */
    public function update(int $id): void
    {
        $this->change(fn (): mixed => $this->service->update(
            $id,
            $this->userId(),
            (int) ($_POST['rating'] ?? 0),
            (string) ($_POST['review'] ?? ''),
            $this->postedTags()
        ), 'Rating updated.');
    }

    /**
     * POST /ratings/{id}/delete — remove my rating within 7 days.
     */
    public function destroy(int $id): void
    {
        $this->change(fn (): mixed => $this->service->delete($id, $this->userId()), 'Rating removed.');
    }

    private function change(callable $action, string $message): void
    {
        try {
            $action();
            $this->flash($message);
        } catch (ValidationException $exception) {
            $this->flash(implode(' ', $exception->errors()), 'error');
        } catch (RecordNotFoundException $exception) {
            $this->notice(404, 'Rating not found', 'Choose one of the ratings you gave.');

            return;
        }

        $this->redirect('/ratings?box=given');
    }

    /**
     * The dialog for ?rate=kind-id or ?edit=id, when the member may use it.
     *
     * @return array{0: array<string, mixed>|null, 1: bool}
     */
    private function dialog(int $me): array
    {
        $edit = (int) ($_GET['edit'] ?? 0);

        if ($edit > 0) {
            try {
                $rating = $this->service->editableOrFail($edit, $me);
            } catch (ValidationException | RecordNotFoundException $exception) {
                return [null, false];
            }

            $tags = json_decode((string) ($rating['tags'] ?? '[]'), true);

            return [[
                'action'  => base_url() . '/ratings/' . $edit,
                'editing' => true,
                'ratee'   => ['initials' => User::initials((string) $rating['ratee_name']), 'name' => (string) $rating['ratee_name'], 'booking' => ucfirst((string) $rating['context'])],
                'stars'   => (int) $rating['stars'],
                'tags'    => is_array($tags) ? $tags : [],
                'review'  => (string) ($rating['comment'] ?? ''),
            ], true];
        }

        $rate = is_string($_GET['rate'] ?? null) ? $_GET['rate'] : '';

        if (preg_match('/^(booking|donation|gift)-([1-9][0-9]*)$/', $rate, $match) !== 1) {
            return [null, false];
        }

        [$kind, $recordId] = [$match[1], (int) $match[2]];
        $subject = RatingService::subject($kind, $this->service->record($kind, $recordId), $me);

        if (is_string($subject) || $this->ratings->byRater($me, $kind, $recordId) !== null) {
            return [null, false];
        }

        $ratee = (new User($this->pdo))->find($subject['ratee']) ?? [];

        return [[
            'action'    => base_url() . '/ratings',
            'kind'      => $kind,
            'record_id' => $recordId,
            'editing'   => false,
            'ratee'     => ['initials' => User::initials((string) ($ratee['full_name'] ?? '')), 'name' => (string) ($ratee['full_name'] ?? ''), 'booking' => ucfirst($subject['context'])],
            'stars'     => 5,
            'tags'      => [],
            'review'    => '',
        ], true];
    }

    /**
     * @return list<string>
     */
    private function postedTags(): array
    {
        $tags = $_POST['tags'] ?? [];

        return is_array($tags) ? array_values(array_map('strval', array_filter($tags, 'is_string'))) : [];
    }
}
