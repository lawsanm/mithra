<?php

declare(strict_types=1);

/**
 * My profile and public profiles (Plan §20.1 module 1.1). Contact details save
 * at once; a new home address waits, with its proof, for the division
 * moderator (ProfileService).
 */
final class ProfileController extends Controller
{
    /**
     * GET /profile.
     */
    public function editForm(): void
    {
        $this->profile($this->userId(), 'profile/edit');
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
            ->personName('full_name', 'Name')
            ->required('phone', 'Mobile number')
            ->maxLength('phone', 'Mobile number', 20)
            ->required('email', 'Email')
            ->maxLength('email', 'Email', 150);

        $input = $validator->values() + ['full_name' => '', 'phone' => '', 'email' => ''];

        if (!$validator->passes()) {
            $this->profile($this->userId(), 'profile/edit', $validator->errors(), $input);

            return;
        }

        try {
            $this->profiles()->updateContact($this->userId(), [
                'full_name' => $input['full_name'],
                'phone'     => $input['phone'],
                'email'     => $input['email'],
            ]);
        } catch (ValidationException $exception) {
            $this->profile($this->userId(), 'profile/edit', $exception->errors(), $input);

            return;
        }

        $this->flash('Your details have been saved.');
        $this->redirect('/profile');
    }

    /**
     * POST /profile/address — a new home address, sent for re-verification.
     */
    public function requestAddressChange(): void
    {
        $validator = new Validator($_POST);
        $validator->required('address', 'New address')->maxLength('address', 'New address', 255)->words('address', 'New address');

        try {
            if (!$validator->passes()) {
                throw new ValidationException($validator->errors());
            }

            $this->profiles()->requestAddressChange(
                $this->userId(),
                $validator->value('address'),
                uploaded_files('address_proof')
            );
        } catch (ValidationException $exception) {
            $this->profile($this->userId(), 'profile/edit', $exception->errors(), ['address' => $validator->value('address')]);

            return;
        }

        $this->flash('Address change sent. Your moderator checks the proof before it replaces your current address.');
        $this->redirect('/profile');
    }

    /**
     * GET /members/{id} — another member's public profile.
     */
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
        $row   = $users->findWithDivision($id);

        if ($row === null) {
            $this->notice(404, 'Member not found', 'This member profile is not available.');

            return;
        }

        $counts = $users->profileStats($id);
        $data   = [
            'member' => $this->summary($row, $counts),
            // My profile is an account page, so a moderator keeps their own
            // navigation there; public profiles belong to the member area.
            'chrome' => $view === 'profile/edit' ? chrome_for($this->role()) : 'member',
        ];

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
            $data['errors']         = $errors;
        } else {
            $data['stats'] = [
                ['label' => 'On-time returns', 'value' => $counts['on_time'] . '%'],
                ['label' => 'Items listed',    'value' => (string) $counts['items']],
                ['label' => 'Times lent',      'value' => (string) $counts['times_lent']],
                ['label' => 'Disputes',        'value' => (string) $counts['disputes']],
            ];
            $data['reviews'] = array_map(
                static fn (array $rating): array => [
                    'initials' => User::initials((string) $rating['counterparty']),
                    'author'   => (string) $rating['counterparty'],
                    'rating'   => (int) $rating['stars'],
                    'text'     => (string) ($rating['comment'] ?? ''),
                    'meta'     => date('j M Y', strtotime((string) $rating['created_at'])),
                ],
                (new Rating($this->pdo))->forMember($id, 'received', 5)
            );
        }

        $this->render($view, $data);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, int>   $counts
     *
     * @return array<string, mixed>
     */
    private function summary(array $row, array $counts): array
    {
        $joined = $row['joined_at'] === null
            ? 'Membership pending'
            : 'member since ' . date('M Y', strtotime((string) $row['joined_at']));

        return [
            'initials'    => User::initials((string) $row['full_name']),
            'name'        => (string) $row['full_name'],
            'verified'    => $row['verified_at'] !== null && $row['membership_status'] === 'active',
            'meta'        => $row['division_name'] . ' GN Division | ' . $joined,
            'donor'       => $counts['donations'] . ' items given',
            'score'       => (string) $row['trust_score'],
            'score_note'  => 'out of 100 | ' . $counts['completed'] . ' completed transactions',
            'public_href' => base_url() . '/members/' . $row['id'],
        ];
    }
}
