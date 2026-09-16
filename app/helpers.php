<?php

declare(strict_types=1);

// Escape text before displaying it in HTML.
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Empty at localhost; /mithra when installed in an Apache subfolder.
function base_url(): string
{
    return defined('APP_BASE') ? APP_BASE : '';
}

// Each session has a random token. Router checks it before a form changes data.
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// Which navigation bar a role uses. Signed-out visitors get the public bar.
function chrome_for(?string $role): string
{
    return match ($role) {
        'member'          => 'member',
        'moderator'       => 'moderator',
        'admin'           => 'admin',
        'sponsor_liaison' => 'sponsor-liaison',
        'sponsor'         => 'sponsor',
        default           => 'public',
    };
}

// Stored upload paths are served through the access-checked proxy (§7.5).
function photo_url(string $path): string
{
    return base_url() . '/photo.php?p=' . rawurlencode($path);
}

/**
 * One form field's uploads as a plain list, whether the input was single or
 * multiple. Controllers call this so services never read $_FILES (§6).
 *
 * @return list<array{name: string, tmp_name: string, error: int, size: int}>
 */
function uploaded_files(string $field): array
{
    $raw = $_FILES[$field] ?? null;

    if (!is_array($raw) || !isset($raw['name'])) {
        return [];
    }

    if (!is_array($raw['name'])) {
        return [[
            'name'     => (string) $raw['name'],
            'tmp_name' => (string) ($raw['tmp_name'] ?? ''),
            'error'    => (int) ($raw['error'] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int) ($raw['size'] ?? 0),
        ]];
    }

    $files = [];

    foreach (array_keys($raw['name']) as $index) {
        $files[] = [
            'name'     => (string) $raw['name'][$index],
            'tmp_name' => (string) ($raw['tmp_name'][$index] ?? ''),
            'error'    => (int) ($raw['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int) ($raw['size'][$index] ?? 0),
        ];
    }

    return $files;
}
