<?php

declare(strict_types=1);

/**
 * A refusal the visitor is allowed to see: the page or record is missing, or it
 * is not theirs. Deliberately says nothing a probe could learn from (§8, fail
 * closed).
 *
 * @var string $noticeTitle
 * @var string $noticeBody
 */

$noticeTitle = $noticeTitle ?? 'Something went wrong';
$noticeBody  = $noticeBody ?? 'That page is not available.';

$pageTitle = $noticeTitle;
$navActive = '';

// Rendered by controllers and by the Router before any database work, so the
// navigation comes from the session role alone.
$chrome   = chrome_for(isset($_SESSION['role']) ? (string) $_SESSION['role'] : null);
$backPath = match ($chrome) {
    'public' => '/login',
    'member' => '/dashboard',
    default  => '/' . $chrome . '/dashboard',
};
include __DIR__ . '/../../partials/header.php';

?>

<div class="empty-state">
    <p class="empty-state__title"><?= e($noticeTitle) ?></p>
    <p class="empty-state__body"><?= e($noticeBody) ?></p>
    <a class="btn btn--primary" href="<?= e(base_url() . $backPath) ?>"><?= $chrome === 'public' ? 'Go to sign in' : 'Back to dashboard' ?></a>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
