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

include __DIR__ . '/../../partials/header.php';

?>

<div class="empty-state">
    <p class="empty-state__title"><?= e($noticeTitle) ?></p>
    <p class="empty-state__body"><?= e($noticeBody) ?></p>
    <a class="btn btn--primary" href="<?= base_url() ?>/items">Back to My Items</a>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
