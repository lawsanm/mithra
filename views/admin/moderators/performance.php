<?php

declare(strict_types=1);

/**
 * Moderator performance overview.
 *
 * @var array $alert         message(string|null)
 * @var array $moderators    initials, name, division, avg_days, monthly_resolved, bond, status, status_label, action_label, action_href
 * @var array $candidates    initials, name, division, trust_score, tenure, scorecard
 */

$alert ??= ['message' => 'Inactivity flag: the Dehiwala moderator has taken no queue actions for 60 days. Review and consider replacement from the eligible candidate pool.'];

$moderators ??= [
    [
        'initials'         => 'JK',
        'name'             => 'J. Kavipriya',
        'division'         => 'Wellawatte',
        'meta'             => 'Avg 1.8 days to clear queues · 31 resolved this month · bond 500 pts',
        'status'           => 'success',
        'status_label'     => 'Active',
        'action_label'     => 'View',
        'action_href'      => base_url() . '/admin/moderators/1',
        'action_style'     => 'ghost',
    ],
    [
        'initials'         => 'AA',
        'name'             => 'A. Akalvily',
        'division'         => 'Kollupitiya',
        'meta'             => 'Avg 2.1 days · 24 resolved this month · bond 500 pts',
        'status'           => 'success',
        'status_label'     => 'Active',
        'action_label'     => 'View',
        'action_href'      => base_url() . '/admin/moderators/2',
        'action_style'     => 'ghost',
    ],
    [
        'initials'         => 'TM',
        'name'             => 'T.H.K. Madushan',
        'division'         => 'Dehiwala',
        'meta'             => 'No queue actions in 60 days · 12 items pending',
        'status'           => 'warning',
        'status_label'     => 'Inactive - 60 days',
        'action_label'     => 'Replace',
        'action_href'      => base_url() . '/admin/moderators/appoint/3',
        'action_style'     => 'ghost',
    ],
];

$candidates ??= [
    [
        'initials'  => 'ML',
        'name'      => 'M. Lawsan',
        'division'  => 'Kollupitiya',
        'meta'      => 'Kollupitiya · Trust 96 · 14 months tenure · scorecard 17/20',
    ],
    [
        'initials'  => 'JK',
        'name'      => 'J. Kavipriya',
        'division'  => 'Dehiwala',
        'meta'      => 'Dehiwala resident · Trust 98 · scorecard 18/20',
    ],
];

$pageTitle = 'Moderator performance';
$navActive = 'moderators';

include __DIR__ . '/../../../partials/header-admin.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Moderator performance</h1>
</header>

<?php if ($alert['message'] !== null): ?>
    <div class="notice notice--warning notice--full">
        <?= e($alert['message']) ?>
    </div>
<?php endif; ?>

<ul class="row-list">
    <?php foreach ($moderators as $mod): ?>
        <li class="list-row">
            <span class="avatar"><?= e($mod['initials']) ?></span>
            <div class="list-row__body">
                <span class="list-row__title"><?= e($mod['name']) ?> - <?= e($mod['division']) ?></span>
                <span class="list-row__meta"><?= e($mod['meta']) ?></span>
            </div>
            <span class="badge badge--<?= e($mod['status']) ?>"><?= e($mod['status_label']) ?></span>
            <a class="btn btn--<?= e($mod['action_style']) ?>" href="<?= e($mod['action_href']) ?>"><?= e($mod['action_label']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<section class="section">
    <h2 class="section__title">Eligible candidate pool - normal operations</h2>

    <ul class="row-list">
        <?php foreach ($candidates as $candidate): ?>
            <li class="list-row">
                <span class="avatar"><?= e($candidate['initials']) ?></span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($candidate['name']) ?></span>
                    <span class="list-row__meta"><?= e($candidate['meta']) ?></span>
                </div>
                <form method="post" action="<?= base_url() ?>/admin/moderators/appoint">
                    <?= csrf_field() ?>
                    <input type="hidden" name="candidate" value="<?= e($candidate['initials']) ?>">
                    <button class="btn btn--primary" type="submit">Appoint</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
