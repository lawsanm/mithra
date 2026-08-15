<?php

declare(strict_types=1);

/**
 * View helpers. Loaded once by the front controller (and by the view preview
 * harness) before any template is rendered. See Rules/CONVENTIONS.md §7.
 */

if (!function_exists('e')) {
    /**
     * Escape a value for output inside HTML. Every dynamic value printed by a
     * view goes through this — no exceptions, including values that came from us.
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    /**
     * The URL prefix the app is served under: '' when it owns the domain root
     * (the dev server on :8123), '/mithra' when Apache serves it from a
     * sub-directory. Links in views are written as
     * href="<?= base_url() ?>/items" so the same markup works in both.
     *
     * The entry point sets APP_BASE before any view renders.
     */
    function base_url(): string
    {
        return defined('APP_BASE') ? APP_BASE : '';
    }
}

if (!function_exists('csrf_token')) {
    /**
     * The current session's CSRF token, generated on first use.
     */
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
}

if (!function_exists('csrf_field')) {
    /**
     * Hidden CSRF input. Every <form> includes this; CsrfMiddleware verifies it
     * on every state-changing request.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}
