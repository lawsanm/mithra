<?php

declare(strict_types=1);

/** Integration checks against the migrated local database; all writes roll back. */
require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/helpers.php';

session_start();
$pdo = Database::connection();
$checks = 0;
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function verify(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

/** Screens that have their own controller now, rendered the way the router does. */
const CONTROLLER_SCREENS = [
    'ratings/index'               => ['RatingController', 'index'],
    'trust/index'                 => ['TrustController', 'show'],
    'notifications/index'         => ['NotificationController', 'index'],
    'sponsor/notifications/index' => ['NotificationController', 'index'],
    'gifts/index'                 => ['GiftController', 'index'],
    'aid-grants/show'             => ['AidGrantController', 'show'],
    'donations/index'             => ['DonationController', 'show'],
    'donations/handover'          => ['DonationController', 'handover'],
    'community/create'            => ['CommunityController', 'createForm'],
];

function screen(PDO $pdo, int $userId, string $role, string $view, array $params = [], array $query = []): string
{
    $_SESSION = ['user_id' => $userId, 'role' => $role, 'csrf_token' => str_repeat('a', 64)];
    $_GET = $query;
    http_response_code(200);
    ob_start();
    try {
        if (isset(CONTROLLER_SCREENS[$view])) {
            [$class, $action] = CONTROLLER_SCREENS[$view];
            $controller = new $class($pdo);
            isset($params['id']) ? $controller->$action((int) $params['id']) : $controller->$action();
        } else {
            (new DemoController($pdo))->show($view, $params);
        }
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

function liaison(PDO $pdo, string $action, ?int $id = null): string
{
    $_SESSION = ['user_id' => 5, 'role' => 'sponsor_liaison', 'csrf_token' => str_repeat('a', 64)];
    $_GET = [];
    http_response_code(200);
    ob_start();
    try {
        $controller = new SponsorLiaisonController($pdo);
        $id === null ? $controller->$action() : $controller->$action($id);
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

$pdo->beginTransaction();
try {
    $rename = $pdo->prepare('UPDATE users SET full_name = :name WHERE id = :id');
    foreach ([1 => 'DynamicModerator', 2 => 'Dynamic Lender & <Name>', 3 => 'Dynamic Neighbour', 4 => 'DynamicMember', 5 => 'DynamicLiaison'] as $id => $name) {
        $rename->execute(['name' => $name, 'id' => $id]);
    }
    $pdo->prepare('UPDATE gn_divisions SET name = ? WHERE id = 1')->execute(['Dynamic Division']);
    $pdo->prepare('UPDATE sponsors SET company_name = ? WHERE id = 1')->execute(['Dynamic Sponsor']);
    $pdo->prepare('UPDATE items SET title = ? WHERE id = 1')->execute(['Dynamic Drill']);
    $pdo->prepare('UPDATE aid_grants SET requested_amount = ?, purpose = ? WHERE id = 1042')->execute([321, 'Dynamic purpose']);
    $pdo->prepare('UPDATE member_wallets SET balance = ? WHERE user_id = 4')->execute([4321]);

    $cases = [
        [4, 'member', 'dashboard/index', [], ['DynamicMember', 'Dynamic Division', 'Dynamic Drill', '4,321 pts']],
        [4, 'member', 'wallet/index', [], ['4,321 pts', 'Dynamic Drill', 'Dynamic Neighbour']],
        [4, 'member', 'ratings/index', [], [e('Dynamic Lender & <Name>'), 'Dynamic Neighbour']],
        [4, 'member', 'trust/index', [], ['Dynamic Division', 'How your score is built']],
        [4, 'member', 'notifications/index', [], [e('Dynamic Lender & <Name>'), 'Dynamic Drill', 'Dynamic Neighbour', 'DynamicModerator']],
        [4, 'member', 'gifts/index', [], ['Dynamic Neighbour']],
        [4, 'member', 'aid-grants/show', ['id' => '1042'], ['321 pts', 'Dynamic purpose', 'DynamicModerator', 'Dynamic Division']],
        [4, 'member', 'donations/index', ['id' => '1'], ['Dynamic Neighbour', 'DynamicModerator', 'Dynamic Division']],
        [4, 'member', 'community/create', [], ['Dynamic Division']],
        [4, 'member', 'transparency/index', [], ['Dynamic Sponsor']],
        [1, 'moderator', 'moderator/dashboard/index', [], ['DynamicModerator', 'Dynamic Division']],
        [1, 'moderator', 'moderator/aid-vouching/index', [], ['DynamicMember', '321 pts', 'Dynamic purpose']],
        [5, 'sponsor_liaison', 'sponsor-liaison/dashboard/index', [], ['DynamicLiaison', 'Dynamic Sponsor', '321 pts', 'DynamicMember']],
        [5, 'sponsor_liaison', 'sponsor-liaison/csr-reports/index', [], ['Dynamic Sponsor']],
        [5, 'sponsor_liaison', 'sponsor-liaison/disasters/index', [], ['Dynamic Division']],
        [7, 'sponsor', 'sponsor/dashboard/index', [], ['Dynamic Sponsor', '10,000']],
        [7, 'sponsor', 'sponsor/branding/edit', [], ['Dynamic Sponsor']],
        [7, 'sponsor', 'sponsor/csr-reports/index', [], ['10,000', '7,000', '3,000']],
    ];
    foreach ($cases as [$userId, $role, $view, $params, $expected]) {
        $body = screen($pdo, $userId, $role, $view, $params);
        verify(http_response_code() === 200, $view . ': failed to render');
        foreach ($expected as $text) {
            verify(str_contains($body, $text), $view . ': missing current data ' . $text);
            $checks++;
        }
        verify(!str_contains($body, 'Northwind Co'), $view . ': sample sponsor leaked');
    }
    $detail = liaison($pdo, 'grant', 1042);
    verify(str_contains($detail, '321 pts') && str_contains($detail, 'Dynamic purpose') && str_contains($detail, 'DynamicMember'), 'Liaison must read the same grant');
    $checks++;
    $purchase = liaison($pdo, 'purchase', 1);
    verify(str_contains($purchase, 'Dynamic Sponsor') && str_contains($purchase, 'DynamicLiaison'), 'Contribution must join sponsor and recorder');
    $checks++;

    // Private member records may not be opened merely by guessing their IDs.
    foreach (['aid-grants/show' => 1042, 'donations/index' => 1, 'donations/handover' => 1] as $view => $id) {
        $body = screen($pdo, 2, 'member', $view, ['id' => (string) $id]);
        verify(http_response_code() === 404 && !str_contains($body, 'Dynamic purpose'), $view . ': ownership failure');
        $checks++;
    }
    $empty = screen($pdo, 2, 'member', 'aid-grants/show');
    verify(str_contains($empty, 'No active aid grant') && !str_contains($empty, '#A-1042'), 'Null grant must remain empty');
    $checks++;
    $notifications = screen($pdo, 7, 'sponsor', 'sponsor/notifications/index');
    verify(!str_contains($notifications, 'Dynamic Drill'), 'Another member notification leaked into sponsor account');
    $checks++;

    // Newly persisted contribution IDs must work on both sides of the same event.
    $eventId = (int) $pdo->query('SELECT id FROM disaster_events WHERE gn_division_id = 1 ORDER BY id DESC LIMIT 1')->fetchColumn();
    $pdo->prepare("INSERT INTO disaster_contributions (disaster_event_id, sponsor_id, description, estimated_value, verified_by, contribution_kind, status)
                   VALUES (?, 1, 'Dynamic relief contribution', 1234, 5, 'goods', 'awaiting')")->execute([$eventId]);
    $id = (string) $pdo->lastInsertId();
    foreach ([[5, 'sponsor_liaison', 'sponsor-liaison/disasters/contributions/show'],
              [5, 'sponsor_liaison', 'sponsor-liaison/disasters/contributions/edit'],
              [1, 'moderator', 'moderator/disasters/contributions/confirm']] as [$userId, $role, $view]) {
        $body = screen($pdo, $userId, $role, $view, ['id' => $id]);
        verify(http_response_code() === 200 && str_contains($body, 'Dynamic relief contribution') && str_contains($body, 'Dynamic Sponsor'), $view . ': wrong contribution');
        $checks++;
    }
    // Sponsor isolation: only its linked company's contributions count.
    $pdo->prepare('UPDATE sponsor_contributions SET cash_amount = 9999, general_points = 9999, aid_points = 0 WHERE sponsor_id = 2')->execute();
    $body = screen($pdo, 7, 'sponsor', 'sponsor/csr-reports/index');
    verify(!str_contains($body, '9,999') && str_contains($body, '10,000'), 'Sponsor totals included another sponsor');
    $checks++;
} finally {
    $pdo->rollBack();
    restore_error_handler();
}

echo "Passed {$checks} dynamic data checks; all database changes rolled back.\n";
