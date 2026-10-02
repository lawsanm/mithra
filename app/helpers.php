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

// URL of a file under /public, stamped with its last change so a browser
// fetches the new copy after every edit instead of reusing a stale one.
function asset_url(string $path): string
{
    $file = __DIR__ . '/../public/' . ltrim($path, '/');
    $stamp = is_file($file) ? '?v=' . filemtime($file) : '';

    return base_url() . '/' . ltrim($path, '/') . $stamp;
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

// A form field's validation message, or nothing when the field passed.
function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<span class="field__error">' . e($errors[$field]) . '</span>' : '';
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

// Where each role lands after signing in, and where the logo sends a signed-in
// account that opens the public home page.
function home_for(string $role): string
{
    return match ($role) {
        'admin'           => '/admin',
        'moderator'       => '/moderator',
        'sponsor_liaison' => '/sponsor-liaison',
        'sponsor'         => '/sponsor',
        default           => '/dashboard',
    };
}

// Stored upload paths are served through the access-checked proxy (§7.5).
function photo_url(string $path): string
{
    return base_url() . '/photo.php?p=' . rawurlencode($path);
}

/**
 * Stored photos as a page lists them: proxy URLs labelled "Photo 1", "Photo 2"…
 *
 * @param  list<string> $paths
 * @return list<array{url: string, label: string}>
 */
function photo_list(array $paths, string $label): array
{
    $photos = [];

    foreach (array_values($paths) as $index => $path) {
        $photos[] = ['url' => photo_url($path), 'label' => $label . ' ' . ($index + 1)];
    }

    return $photos;
}

// A count with its noun: "1 request", "3 requests".
function plural(int $count, string $word): string
{
    return $count . ' ' . $word . ($count === 1 ? '' : 's');
}

// A rental's rates as members read them, e.g. "80 pts / day  ·  1,500 pts / month".
function rate_label(mixed $daily, mixed $monthly): string
{
    $rates = [];

    if ((int) $daily > 0) {
        $rates[] = number_format((int) $daily) . ' pts / day';
    }

    if ((int) $monthly > 0) {
        $rates[] = number_format((int) $monthly) . ' pts / month';
    }

    return $rates === [] ? 'Rate not set' : implode('  ·  ', $rates);
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
