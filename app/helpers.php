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
