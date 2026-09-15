<?php

declare(strict_types=1);

/**
 * My profile and public profiles (Plan §20.1 module 1.1). Contact details save
 * at once; a new home address waits, with its proof, for the division
 * moderator (ProfileService).
 */
final class ProfileController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function editForm(): void
    {
        $this->profile($this->memberId(), 'profile/edit');
    }

    /**
     * POST /profile — name, mobile and email.
     */
    public function update(): void
    {
        $validator = new Validator($_POST);
        $validator
            ->required('full_name', 'Name')
            ->maxLength('full_name', 'Name', 150)
            ->required('phone', 'Mobile number')
            ->maxLength('phone', 'Mobile number', 20)
            ->maxLength('email', 'Email', 150);

        $input = $validator->values() + ['full_name' => '', 'phone' => '', 'email' => ''];

        if (!$validator->passes()) {
            $this->profile($this->memberId(), 'profile/edit', $validator->errors(), $input);

            return;
        }

        try {
            $this->service()->updateContact($this->memberId(), [
                'full_name' => $input['full_name'],
                'phone'     => $input['phone'],
                'email'     => $input['email'],
            ]);
        } catch (ValidationException $exception) {
            $this->profile($this->memberId(), 'profile/edit', $exception->errors(), $input);

            return;
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Your details have been saved.'];
        header('Location: ' . base_url() . '/profile', true, 303);
    }

    /**
     * POST /profile/address — a new home address, sent for re-verification.
     */
    public function requestAddressChange(): void
    {
        $validator = new Validator($_POST);
        $validator->required('address', 'New address')->maxLength('address', 'New address', 255);

        try {
            if (!$validator->passes()) {
                throw new ValidationException($validator->errors());
            }

            $this->service()->requestAddressChange(
                $this->memberId(),
                $validator->value('address'),
                uploaded_files('address_proof')
            );
        } catch (ValidationException $exception) {
            $this->profile($this->memberId(), 'profile/edit', $exception->errors(), ['address' => $validator->value('address')]);

            return;
        }

        $_SESSION['flash'] = [
            'type'    => 'success',
            'message' => 'Address change sent. Your moderator checks the proof before it replaces your current address.',
        ];
        header('Location: ' . base_url() . '/profile', true, 303);
    }

    private function service(): ProfileService
    {
        return new ProfileService(
            $this->pdo,
            new User($this->pdo),
            new UserDivision($this->pdo),
            new AddressChange($this->pdo),
            new GnDivision($this->pdo),
            new PhotoStore(dirname(__DIR__, 2) . '/storage/uploads')
        );
    }

    public function show(int $id): void
    {
        $this->profile($id, 'profile/show');
    }

    /**
     * @param array<string, string> $errors per-field messages from a failed save
     * @param array<string, string> $input  what was typed, shown back after a failure
     */
    private function profile(int $id, string $view, array $errors = [], array $input = []): void
    {
        $users = new User($this->pdo);
        $row = $users->findWithDivision($id);
        if ($row === null) {
            http_response_code(404);
            $this->render('errors/notice', [
                'noticeTitle' => 'Member not found',
                'noticeBody' => 'This member profile is not available.',
            ]);
            return;
        }

        $counts = $users->profileStats($id);
        $data = ['member' => $this->summary($row, $counts)];
        if ($view === 'profile/edit') {
            if ($errors !== []) {
                http_response_code(422);
            }
            $data['draft'] = [
                'full_name' => $input['full_name'] ?? (string) $row['full_name'],
                'phone'     => $input['phone'] ?? (string) $row['phone'],
                'email'     => $input['email'] ?? (string) ($row['email'] ?? ''),
                'address'   => $input['address'] ?? '',
            ];
            $data['currentAddress'] = (string) $row['address'];
            $data['addressChange']  = (new AddressChange($this->pdo))->latestFor($id);
            $data['errors'] = $errors;
            $data['flash']  = $this->takeFlash();
        } else {
            $data['stats'] = [
                ['label' => 'On-time returns', 'value' => $counts['on_time'] . '%'],
                ['label' => 'Items listed', 'value' => (string) $counts['items']],
                ['label' => 'Times lent', 'value' => (string) $counts['times_lent']],
                ['label' => 'Disputes', 'value' => (string) $counts['disputes']],
            ];
            $data['reviews'] = array_map(
                static fn (array $rating): array => [
                    'initials' => User::initials((string) $rating['counterparty']),
                    'author' => (string) $rating['counterparty'],
                    'rating' => (int) $rating['stars'],
                    'text' => (string) ($rating['comment'] ?? ''),
                    'meta' => date('j M Y', strtotime((string) $rating['created_at'])),
                ],
                (new Rating($this->pdo))->forMember($id, 'received', 5)
            );
        }
        $this->render($view, $data);
    }

    private function summary(array $row, array $counts): array
    {
        $joined = $row['joined_at'] === null
            ? 'Membership pending'
            : 'member since ' . date('M Y', strtotime((string) $row['joined_at']));

        return [
            'initials' => User::initials((string) $row['full_name']),
            'name' => (string) $row['full_name'],
            'verified' => $row['verified_at'] !== null && $row['membership_status'] === 'active',
            'meta' => $row['division_name'] . ' GN Division | ' . $joined,
            'donor' => $counts['donations'] . ' items given',
            'score' => (string) $row['trust_score'],
            'score_note' => 'out of 100 | ' . $counts['completed'] . ' completed transactions',
            'public_href' => base_url() . '/members/' . $row['id'],
        ];
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function takeFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return is_array($flash) ? ['type' => (string) $flash['type'], 'message' => (string) $flash['message']] : null;
    }

    /** The signed-in member. AuthMiddleware guarantees there is one (§7.4). */
    private function memberId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    private function render(string $view, array $data): void
    {
        $viewer = (new User($this->pdo))->findWithDivision($this->memberId());
        $data['currentMember'] = [
            'initials' => User::initials((string) ($viewer['full_name'] ?? '')),
            'points_balance' => number_format((new Wallet($this->pdo))->balance($this->memberId())) . ' pts',
        ];
        extract($data, EXTR_SKIP);
        require dirname(__DIR__, 2) . '/views/' . $view . '.php';
    }
}
