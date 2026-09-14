<?php

declare(strict_types=1);

/**
 * Photo proxy (Rules/CONVENTIONS.md §7.5).
 *
 * Uploads live in /storage/uploads, outside the web root, so Apache cannot
 * serve them and nobody can guess their way through the folder. Every request
 * lands here instead and is answered only when the path is both well formed and
 * actually referenced — by a live listing, or by the asking member's own
 * half-finished create wizard.
 *
 *     <img src="/photo.php?p=item-photos/<32 hex chars>.jpg">
 */

require_once __DIR__ . '/../app/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$requested = (string) ($_GET['p'] ?? '');

$store = new PhotoStore(dirname(__DIR__) . '/storage/uploads');

// Rejects anything that is not folder/<32 hex>.jpg, so "../" never resolves.
$absolute = $store->absolutePath($requested);

if ($absolute === null) {
    http_response_code(404);

    exit;
}

/**
 * Photos uploaded during the create wizard are not on a row yet. They belong to
 * whoever holds this session, and to nobody else.
 */
function photo_is_in_own_draft(string $path): bool
{
    $draft = $_SESSION['item_draft'] ?? null;

    if (!is_array($draft)) {
        return false;
    }

    if (($draft['value_proof_path'] ?? null) === $path) {
        return true;
    }

    return is_array($draft['photos'] ?? null) && in_array($path, $draft['photos'], true);
}

if (!photo_is_in_own_draft($requested) && !(new Item(Database::connection()))->photoPathExists($requested)) {
    http_response_code(404);

    exit;
}

// Everything stored is re-encoded to JPEG by PhotoStore, so the type is known
// rather than sniffed. nosniff stops a browser second-guessing it.
header('Content-Type: image/jpeg');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . (string) filesize($absolute));
header('Cache-Control: private, max-age=86400');

readfile($absolute);
