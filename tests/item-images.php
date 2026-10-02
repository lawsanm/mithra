<?php

declare(strict_types=1);

/** Read-only image integration checks. Requires migrated MySQL and local Apache. */
require_once __DIR__ . '/common.php';

$pdo = Database::connection();
$store = PhotoStore::uploads();
$base = 'http://localhost/mithra';
$checks = 0;

function imageRequest(string $url, string $cookie = ''): array
{
    $context = stream_context_create(['http' => [
        'header' => $cookie === '' ? '' : 'Cookie: ' . $cookie,
        'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15,
    ]]);
    $body = file_get_contents($url, false, $context);
    return [$http_response_header ?? [], $body === false ? '' : $body];
}

// Test sessions use existing seed accounts, as dynamic-data.php does. No password
// or account data changes; the temporary session is destroyed in finally.
session_id('imagecheck-' . bin2hex(random_bytes(12)));
session_start();
$cookie = session_name() . '=' . session_id();

try {
    foreach (['item-photos/demo/../cordless-drill.jpg', 'item-photos/demo/../../config/config.php',
        'item-photos/demo/cordless-drill.jpg/extra', "item-photos/demo/cordless-drill.jpg\n",
        'value-proofs/demo/cordless-drill.jpg'] as $invalid) {
        check($store->absolutePath($invalid) === null, 'Unsafe photo path accepted');
        $checks++;
    }

    $state = (new User($pdo))->sessionState(4);
    $_SESSION = ['user_id' => 4, 'role' => 'member', 'password_stamp' => $state['password_changed_at'],
        'last_seen' => time(), 'csrf_token' => bin2hex(random_bytes(32))];
    session_write_close();

    $items = $pdo->query('SELECT id, title, photos FROM items ORDER BY id')->fetchAll();
    $catalogCount = 0;
    foreach ($items as $item) {
        foreach (json_decode((string) ($item['photos'] ?? '[]'), true) ?? [] as $path) {
            if (!str_starts_with($path, 'item-photos/demo/')) {
                continue;
            }
            $absolute = $store->absolutePath($path);
            check($absolute !== null, $item['title'] . ': missing image');
            $size = getimagesize($absolute);
            check($size !== false && $size[0] === 1200 && $size[1] === 900 && $size['mime'] === 'image/jpeg', 'Invalid catalog image');
            $store->delete($path);
            check(is_file($absolute), 'Removing a listing photo must preserve bundled assets');
            [$headers, $body] = imageRequest($base . '/photo.php?p=' . rawurlencode($path), $cookie);
            check(str_contains($headers[0] ?? '', '200'), $item['title'] . ': photo proxy failed');
            check(hash('sha256', $body) === hash_file('sha256', $absolute), 'Proxy returned the wrong image');
            $checks += 5;
            $catalogCount++;
        }
    }
    check($catalogCount === 22, 'Expected all 22 seeded item images');

    [$headers] = imageRequest($base . '/photo.php?p=item-photos%2Fdemo%2Fcordless-drill.jpg');
    check(str_contains($headers[0] ?? '', '404'), 'Signed-out visitors must not access item photos');
    $checks++;

    foreach (['/dashboard', '/items', '/items/browse', '/items/1', '/items/10/edit', '/bookings', '/donations/1/handover'] as $path) {
        [$headers, $body] = imageRequest($base . $path, $cookie);
        check(str_contains($headers[0] ?? '', '200'), $path . ': page failed');
        check(str_contains($body, 'item-photos%2Fdemo%2F'), $path . ': missing catalog images');
        check(!str_contains($body, '>Photo</span>'), $path . ': stale placeholder');
        $checks += 3;
    }

    session_start();
    $state = (new User($pdo))->sessionState(1);
    $_SESSION = ['user_id' => 1, 'role' => 'moderator', 'password_stamp' => $state['password_changed_at'],
        'last_seen' => time(), 'csrf_token' => bin2hex(random_bytes(32))];
    session_write_close();
    foreach (['/moderator', '/moderator/listing-approvals', '/moderator/listing-approvals/12'] as $path) {
        [$headers, $body] = imageRequest($base . $path, $cookie);
        check(str_contains($headers[0] ?? '', '200'), $path . ': page failed');
        check(str_contains($body, 'item-photos%2Fdemo%2F'), $path . ': missing catalog images');
        $checks += 2;
    }

    // Photo requests bypass the router; stale sessions must still be refused.
    $state = (new User($pdo))->sessionState(4);
    $valid = ['user_id' => 4, 'role' => 'member', 'password_stamp' => $state['password_changed_at'], 'last_seen' => time()];
    foreach ([
        ['last_seen' => time() - SessionMiddleware::IDLE_SECONDS - 1],
        ['password_stamp' => 'stale-password-stamp'],
        ['role' => 'admin'],
        ['user_id' => PHP_INT_MAX],
    ] as $stale) {
        session_start();
        $_SESSION = $stale + $valid;
        session_write_close();
        [$headers] = imageRequest($base . '/photo.php?p=item-photos%2Fdemo%2Fcordless-drill.jpg', $cookie);
        check(str_contains($headers[0] ?? '', '404'), 'A stale session must not access stored photos');
        $checks++;
    }
} finally {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION = [];
    session_destroy();
}

echo 'Passed ' . $checks . " image checks: 22 assets, access controls and 10 screens.\n";
