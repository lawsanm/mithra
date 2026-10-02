<?php

declare(strict_types=1);

/**
 * My profile and public profiles (Plan §20.1 module 1.1). Contact details save
 * at once; a new home address waits, with its proof, for the division
 * moderator (ProfileService).
 */
final class ProfileController extends Controller
{
    /** user_divisions.status → badge text and tone. */
    private const MEMBERSHIP_STATUS = [
        'active'      => ['Active', 'success'],
        'pending'     => ['Pending verification', 'warning'],
        'paused'      => ['Paused', 'warning'],
        'expired'     => ['Expired', 'neutral'],
        'rejected'    => ['Rejected', 'error'],
        'deactivated' => ['Ended', 'neutral'],
    ];

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
        $row = (new User($this->pdo))->findWithDivision($id);

        // Only active members have a public profile (I7); you always see your own.
        if ($id !== $this->userId() && ($row === null || $row['status'] !== 'active' || $row['membership_status'] !== 'active')) {
            $this->notice(404, 'Member not found', 'This member profile is not available.');

            return;
        }

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
            'member' => $this->summary($row, $counts) + ['context' => $this->trustContext($id, $row)],
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
            $data['communities']    = $this->communities($row, (new UserDivision($this->pdo))->temporaryForUser($id));
            $data['errors']         = $errors;
        } else {
            $data['stats'] = [
                ['label' => 'On-time returns', 'value' => $counts['on_time'] . '%'],
                ['label' => 'Items listed',    'value' => (string) $counts['items']],
                ['label' => 'Times lent',      'value' => (string) $counts['times_lent']],
                ['label' => 'Disputes',        'value' => (string) $counts['disputes']],
            ];
            // §18.4: send a gift straight from a neighbour's profile.
            $data['giftHref'] = in_array($id, array_map(
                static fn (array $row): int => (int) $row['id'],
                $users->giftableExcept($this->userId())
            ), true) ? base_url() . '/gifts?to=' . $id . '#send-gift' : null;

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
     * The member's home community and, if they hold one, their temporary one
     * (Plan §6.5), as rows for the "My communities" panel.
     *
     * @param array<string, mixed>      $home      the findWithDivision() row
     * @param array<string, mixed>|null $temporary the temporaryForUser() row
     *
     * @return list<array{label:string, name:string, status:string, tone:string, note:string}>
     */
    private function communities(array $home, ?array $temporary): array
    {
        $rows = [[
            'label'  => 'Home',
            'name'   => $home['division_name'] . ' GN Division',
            'status' => self::MEMBERSHIP_STATUS[$home['membership_status']][0] ?? ucfirst((string) $home['membership_status']),
            'tone'   => self::MEMBERSHIP_STATUS[$home['membership_status']][1] ?? 'neutral',
            'note'   => $home['verified_at'] === null
                ? 'Waiting for your moderator to verify'
                : 'Verified ' . date('j M Y', strtotime((string) $home['verified_at'])),
        ]];

        if ($temporary !== null) {
            $rows[] = [
                'label'  => 'Temporary',
                'name'   => $temporary['name'] . ' GN Division',
                'status' => self::MEMBERSHIP_STATUS[$temporary['status']][0] ?? ucfirst((string) $temporary['status']),
                'tone'   => self::MEMBERSHIP_STATUS[$temporary['status']][1] ?? 'neutral',
                'note'   => $temporary['expires_at'] === null
                    ? 'Requested ' . date('j M Y', strtotime((string) $temporary['created_at']))
                    : 'Expires ' . date('j M Y', strtotime((string) $temporary['expires_at'])),
            ];
        }

        return $rows;
    }

    /**
     * "★ 78 overall · 12 completed in Kollupitiya · 3 in Dehiwala" — where a
     * member's record was earned (Plan §6.3.5).
     *
     * @param array<string, mixed> $row the findWithDivision() row
     */
    private function trustContext(int $id, array $row): string
    {
        $users = new User($this->pdo);
        $parts = [
            '★ ' . $row['trust_score'] . ' overall',
            $users->completedInDivision($id, (int) $row['division_id']) . ' completed in ' . $row['division_name'],
        ];

        $temporary = (new UserDivision($this->pdo))->activeTemporary($id);

        if ($temporary !== null) {
            $parts[] = $users->completedInDivision($id, (int) $temporary['gn_division_id']) . ' in ' . $temporary['division_name'] . ' (temporary)';
        }

        return implode(' · ', $parts);
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
            'is_donor'    => $counts['donations'] > 0,
            'score'       => (string) $row['trust_score'],
            'score_note'  => 'out of 100 | ' . $counts['completed'] . ' completed transactions',
            'public_href' => base_url() . '/members/' . $row['id'],
        ];
    }
}
