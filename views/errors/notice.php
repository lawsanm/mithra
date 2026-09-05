<?php

declare(strict_types=1);

/**
 * A refusal the member is allowed to see: the listing is missing, or it is not
 * theirs. Deliberately says nothing a probe could learn from (§8, fail closed).
 *
 * @var string $noticeTitle
 * @var string $noticeBody
 */

$noticeTitle = $noticeTitle ?? 'Something went wrong';
$noticeBody  = $noticeBody ?? 'That page is not available.';

$pageTitle = $noticeTitle;
$navActive = '';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$rolePrefix = '/' . trim(substr($requestPath, strlen(base_url())), '/');
$errorRole = 'member';
foreach (['sponsor-liaison', 'sponsor', 'moderator', 'admin'] as $role) {
    if ($rolePrefix === '/' . $role || str_starts_with($rolePrefix, '/' . $role . '/')) {
        $errorRole = $role;
        break;
    }
}
$backPath = $errorRole === 'member' ? '/dashboard' : '/' . $errorRole . '/dashboard';
include __DIR__ . '/../../partials/header' . ($errorRole === 'member' ? '' : '-' . $errorRole) . '.php';

?>

<div class="empty-state">
    <p class="empty-state__title"><?= e($noticeTitle) ?></p>
    <p class="empty-state__body"><?= e($noticeBody) ?></p>
    <a class="btn btn--primary" href="<?= e(base_url() . $backPath) ?>">Back to dashboard</a>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
