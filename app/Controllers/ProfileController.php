<?php

declare(strict_types=1);

/** Both profile pages use database members, never sample identities. */
final class ProfileController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function editForm(): void
    {
        $this->profile($this->memberId(), 'profile/edit');
    }

    public function show(int $id): void
    {
        $this->profile($id, 'profile/show');
    }

    private function profile(int $id, string $view): void
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
            $data['draft'] = [
                'display_name' => (string) $row['full_name'],
                'mobile' => (string) $row['phone'],
                'email' => (string) ($row['email'] ?? ''),
                'address' => (string) $row['address'],
            ];
            $data['errors'] = [];
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

    private function memberId(): int
    {
        return (int) ($_SESSION['user_id'] ?? Config::get('demo_member_id', 4));
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
