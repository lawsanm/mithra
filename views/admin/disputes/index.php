<?php

declare(strict_types=1);

/**
 * Escalated disputes list — admin queue.
 *
 * @var array $disputes  rows: title, case_number, division, parties, escalated_at, reason, status, status_label, href
 */

$disputes ??= [
    [
        'title'        => 'Grinding Drill',
        'case_number'  => '#CD-0142',
        'division'     => 'Wellawatte',
        'parties'      => 'T.H.K. Madushan vs M. Lawsan',
        'escalated_at' => 'escalated 18 Jul',
        'reason'       => 'borrower refused sign-off',
        'status'       => 'warning',
        'status_label' => 'Timer expired',
        'href'         => base_url() . '/admin/disputes/142',
    ],
    [
        'title'        => 'Stand Mixer',
        'case_number'  => '#CD-0138',
        'division'     => 'Kollupitiya',
        'parties'      => 'J. Kavipriya vs A. Akalvily',
        'escalated_at' => 'escalated 16 Jul',
        'reason'       => 'day 5 of 7',
        'status'       => 'info',
        'status_label' => 'In window',
        'href'         => base_url() . '/admin/disputes/138',
    ],
];

$pageTitle = 'Escalated disputes';
$navActive = 'disputes';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <div>
        <h1 class="page-header__title">Escalated disputes</h1>
        <p class="page-intro__meta">Cases arrive here when a party refuses the moderator's resolution, after the 7-day sign-off timer.</p>
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

<div class="notice notice--info notice--full">
    Your ruling is final and recorded in the append-only ledger. Points move only after your decision is saved.
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
