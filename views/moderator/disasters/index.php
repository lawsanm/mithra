<?php

declare(strict_types=1);

/**
 * Disaster mode — what the moderator sees while a division-level disaster is
 * active: fast-track aid vouching and the relief actions they can record.
 *
 * @var array  $disaster division, badge and the note under the heading
 * @var array  $stats    two figures for the stat row
 * @var array  $requests aid requests awaiting a vouch: id, initials, name, meta
 */

// Sample view data — replaced by the controller once DisasterController lands.
$disaster ??= [
    'division' => 'Wellawatte Division',
    'note'     => 'Flooding reported 12 Jul  ·  Disaster Mode enables fast-track aid vouching '
                . 'and sponsor coordination',
];

$stats ??= [
    ['label' => 'Active disasters',     'value' => '1', 'note' => 'Flooding · low-lying areas'],
    ['label' => 'Aid requests pending', 'value' => '6', 'note' => 'Fast-track vouching enabled'],
];

$requests ??= [
    [
        'id'       => '1',
        'initials' => 'AA',
        'name'     => 'A. Akalvily',
        'meta'     => 'Requesting dry rations and drinking water  ·  household of 4  ·  requested 15 Jul',
    ],
    [
        'id'       => '2',
        'initials' => 'TM',
        'name'     => 'T.H.K. Madushan',
        'meta'     => 'Requesting temporary shelter tarp  ·  roof damage  ·  requested 16 Jul',
    ],
];

$pageTitle = 'Disaster relief';
$navActive = 'disasters';

include __DIR__ . '/../../../partials/header-moderator.php';

?>

<header class="record-head">
    <h1 class="record-head__title">Disaster relief — <?= e($disaster['division']) ?></h1>
    <span class="badge badge--error">
        <span aria-hidden="true">!</span>
        Disaster Mode active
    </span>
</header>

<p class="record-meta"><?= e($disaster['note']) ?></p>

<div class="stat-grid stat-grid--2">
    <?php foreach ($stats as $stat): ?>
        <div class="stat-card">
            <span class="stat-card__label"><?= e($stat['label']) ?></span>
            <strong class="stat-card__value stat-card__value--primary"><?= e($stat['value']) ?></strong>
            <span class="stat-card__note"><?= e($stat['note']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Aid requests awaiting your vouch</h2>
        <a class="link section__action" href="<?= base_url() ?>/moderator/aid-vouching">View all</a>
    </div>

    <ul class="row-list">
        <?php foreach ($requests as $request): ?>
            <li class="list-row">
                <span class="avatar avatar--md"><?= e($request['initials']) ?></span>
                <div class="list-row__body">
                    <span class="list-row__title"><?= e($request['name']) ?></span>
                    <span class="list-row__meta"><?= e($request['meta']) ?></span>
                </div>
                <a class="btn btn--ghost" href="<?= base_url() ?>/aid-grants/<?= rawurlencode($request['id']) ?>">View request</a>
                <form method="post" action="<?= base_url() ?>/moderator/aid-vouching/<?= rawurlencode($request['id']) ?>/vouch">
                    <?= csrf_field() ?>
                    <button class="btn btn--primary" type="submit">Vouch</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="panel">
    <div class="panel__head">
        <div class="media__body">
            <h2 class="panel__title">Relief actions</h2>
            <p class="panel__note">
                Report a new disaster to Admin, or record relief items handed out in your division.
            </p>
        </div>
        <div class="actions panel__actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/moderator/disasters/report">Report disaster</a>
            <a class="btn btn--primary" href="<?= base_url() ?>/moderator/disasters/relief">Record relief given</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
