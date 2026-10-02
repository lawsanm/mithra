<?php

declare(strict_types=1);

/**
 * Photo proxy (Plan §21.1, Rules/CONVENTIONS.md §7.5).
 *
 * Uploads live in /storage/uploads, outside the web root, so Apache cannot
 * serve them and nobody can guess their way through the folder. Every request
 * lands here instead and is answered only for a signed-in account, and only
 * when the path is well formed and this account may see it:
 *
 *   identity-documents/  the division's moderator and the Admin (Plan §25.3),
 *                        including proofs sent with an address change
 *   value-proofs/        the item's owner, its division's moderator, the Admin
 *   handover-photos/,    the booking's lender and borrower, the moderator of
 *   return-photos/,      the item's division, the Admin (Plan §10.1)
 *   damage-evidence/
 *   aid-evidence/        the member, their division's moderator, the Sponsor
 *                        Liaison, the Admin (Plan §12)
 *   item-photos/         any signed-in account, while the listing is live
 *
 * plus anything in the asking member's own half-finished create wizard.
 *
 *     <img src="/photo.php?p=item-photos/<32 hex chars>.jpg">
 */

require_once __DIR__ . '/../app/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$requested = is_string($_GET['p'] ?? null) ? $_GET['p'] : '';

$store = new PhotoStore(dirname(__DIR__) . '/storage/uploads');

// Accepts generated upload names and bundled demo item photos; rejects traversal.
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

/**
 * Whether the signed-in account may see this stored file. Fail closed: an
 * unknown folder or an unreferenced path is refused (§8).
 */
function photo_is_visible(string $path, int $userId, string $role): bool
{
    if (photo_is_in_own_draft($path)) {
        return true;
    }

    $pdo    = Database::connection();
    $folder = strstr($path, '/', true);

    if ($folder === RegistrationService::DOCUMENT_FOLDER) {
        $memberships = new UserDivision($pdo);
        $changes     = new AddressChange($pdo);

        if ($role === 'admin') {
            return $memberships->isDocument($path) || $changes->isProof($path);
        }

        return $role === 'moderator'
            && (in_array($userId, $memberships->documentReviewers($path), true)
                || in_array($userId, $changes->proofReviewers($path), true));
    }

    if ($folder === ItemService::PROOF_FOLDER) {
        $viewers = (new Item($pdo))->valueProofViewers($path);

        if ($viewers === null) {
            return false;
        }

        return $role === 'admin' || in_array($userId, $viewers, true);
    }

    if ($folder === 'handover-photos' || $folder === 'return-photos') {
        return photo_viewer_allowed((new Booking($pdo))->conditionPhotoViewers($path), $userId, $role === 'admin');
    }

    if ($folder === 'damage-evidence') {
        return photo_viewer_allowed((new DamageClaim($pdo))->evidenceViewers($path), $userId, $role === 'admin');
    }

    if ($folder === 'aid-evidence') {
        return photo_viewer_allowed(
            (new AidGrant($pdo))->evidenceViewers($path),
            $userId,
            in_array($role, ['admin', 'sponsor_liaison'], true)
        );
    }

    return (new Item($pdo))->photoPathExists($path);
}

/**
 * @param list<int>|null $viewers null when no record carries the path
 */
function photo_viewer_allowed(?array $viewers, int $userId, bool $byRole): bool
{
    return $viewers !== null && ($byRole || in_array($userId, $viewers, true));
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$role   = (string) ($_SESSION['role'] ?? '');

// This endpoint bypasses Router, so it must apply the same session rules before
// accepting a cached role or granting access to a draft or private document.
$allowed = false;

try {
    if ($userId > 0) {
        $ended = (new SessionMiddleware())->handle(
            (new User(Database::connection()))->sessionState($userId),
            $role,
            isset($_SESSION['password_stamp']) ? (string) $_SESSION['password_stamp'] : null,
            isset($_SESSION['last_seen']) ? (int) $_SESSION['last_seen'] : null,
            time()
        );

        $allowed = $ended === null && photo_is_visible($requested, $userId, $role);
    }
} catch (Throwable $exception) {
    error_log((string) $exception);
}

if (!$allowed) {
    http_response_code(404);

    exit;
}

// Everything stored is re-encoded to JPEG by PhotoStore, so the type is known
// rather than sniffed. nosniff stops a browser second-guessing it.
header('Content-Type: image/jpeg');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . (string) filesize($absolute));
header('Cache-Control: private, no-store');

readfile($absolute);
