<?php

declare(strict_types=1);

/**
 * Escalated disputes list — admin queue.
 *
 * @var array $disputes  rows: title, case_number, division, parties, escalated_at, reason, status, status_label, href
 * @var int   $page
 * @var bool  $hasNextPage
 */

$pageTitle = 'Escalated disputes';
$navActive = 'disputes';

$chrome = 'admin';
include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <div>
        <h1 class="page-header__title">Escalated disputes</h1>
        <p class="page-intro__meta">Review cases escalated from moderator mediation, including bookings involving the division moderator.</p>
    </div>
</header>

<ul class="row-list">
    <?php foreach ($disputes as $dispute): ?>
        <li class="list-row">
            <div class="list-row__body">
                <span class="list-row__title"><?= e($dispute['title']) ?> · <?= e($dispute['case_number']) ?></span>
                <span class="list-row__meta">
                    <?= e($dispute['division']) ?> · <?= e($dispute['parties']) ?> · <?= e($dispute['escalated_at']) ?> · <?= e($dispute['reason']) ?>
                </span>
            </div>
            <span class="badge badge--<?= e($dispute['status']) ?>"><?= e($dispute['status_label']) ?></span>
            <a class="btn btn--primary" href="<?= e($dispute['href']) ?>">Review</a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($disputes === []): ?>
    <p class="empty-state__body">There are no open disputes to review.</p>
<?php endif; ?>

<nav class="actions" aria-label="Dispute queue pagination">
    <?php if ($page > 1): ?><a class="btn btn--ghost" href="?page=<?= e((string) ($page - 1)) ?>">Previous</a><?php endif; ?>
    <?php if ($hasNextPage): ?><a class="btn btn--ghost" href="?page=<?= e((string) ($page + 1)) ?>">Next</a><?php endif; ?>
</nav>

<div class="notice notice--info notice--full">
    Review the parties’ accounts, the in-person moderator record, and the handover and return evidence before making a final ruling.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
