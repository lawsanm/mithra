<?php

declare(strict_types=1);

/**
 * Disaster relief — the division's active disaster, the relief the moderator
 * has handed out, and the aid requests waiting on their vouch.
 *
 * @var array|null $disaster    division and the note under the heading, or null when Disaster Mode is off
 * @var array      $stats       figures for the stat row while a disaster is active
 * @var array      $records     rows: id, title, meta, value, editable
 * @var int        $page        current page
 * @var bool       $hasNextPage whether another page follows
 * @var array      $reports     every disaster in the division: id, title, period, active
 * @var array      $requests   aid requests awaiting a vouch: id, initials, name, meta
 */

$disaster    = $disaster ?? null;
$stats       = $stats ?? [];
$records     = $records ?? [];
$page        = $page ?? 1;
$hasNextPage = $hasNextPage ?? false;
$reports     = $reports ?? [];

// Sample view data — replaced by the controller once AidGrantController lands.
$requests ??= [
    [
        'id'       => '1',
        'initials' => User::initials('N. Arun'),
        'name'     => 'N. Arun',
        'meta'     => 'Requesting dry rations and drinking water  ·  household of 4  ·  requested 15 Jul',
    ],
    [
        'id'       => '2',
        'initials' => User::initials('N. Abishan'),
        'name'     => 'N. Abishan',
        'meta'     => 'Requesting temporary shelter tarp  ·  roof damage  ·  requested 16 Jul',
    ],
];

$pageTitle = 'Disaster relief';
$navActive = 'disasters';

$chrome = 'moderator';
include __DIR__ . '/../../../partials/header.php';

$reliefUrl = base_url() . '/moderator/disasters/relief';

?>

<?php if ($disaster !== null): ?>
    <header class="record-head">
        <h1 class="record-head__title">Disaster relief — <?= e($disaster['division']) ?></h1>
        <span class="badge badge--error">
            <span aria-hidden="true">!</span>
            Disaster Mode active
        </span>
    </header>

    <p class="record-meta"><?= e($disaster['note']) ?></p>
<?php else: ?>
    <header class="record-head">
        <h1 class="record-head__title">Disaster relief</h1>
        <span class="badge badge--success">Normal</span>
    </header>
<?php endif; ?>

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<?php if ($disaster !== null): ?>
    <div class="stat-grid stat-grid--3">
        <?php foreach ($stats as $stat): ?>
            <?php $statTone = 'primary'; include __DIR__ . '/../../../partials/stat-card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <p class="empty-state__title">Disaster Mode is off in your division</p>
        <p class="empty-state__body">
            Relief can be recorded once the Admin activates Disaster Mode. Earlier relief records stay listed below.
        </p>
    </div>
<?php endif; ?>

<section class="section">
    <div class="section__head">
        <h2 class="section__title">Relief handed out</h2>
        <?php if ($disaster !== null): ?>
            <a class="btn btn--primary section__action" href="<?= e($reliefUrl) ?>/create">
                <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-plus"></use></svg>
                Record relief given
            </a>
        <?php endif; ?>
    </div>

    <?php if ($records === []): ?>
        <div class="empty-state">
            <p class="empty-state__title">No relief recorded yet</p>
            <p class="empty-state__body">Each time you hand out sponsored relief, record what was given and where.</p>
        </div>
    <?php else: ?>
        <ul class="row-list">
            <?php foreach ($records as $record): ?>
                <li class="list-row">
                    <div class="list-row__body">
                        <span class="list-row__title">
                            <?= e($record['title']) ?>
                            <?php if (!$record['editable']): ?>
                                <span class="badge badge--neutral">Locked</span>
                            <?php endif; ?>
                        </span>
                        <span class="list-row__meta"><?= e($record['meta']) ?></span>
                    </div>
                    <?php if ($record['value'] !== ''): ?>
                        <strong class="list-row__amount"><?= e($record['value']) ?></strong>
                    <?php endif; ?>
                    <?php if ($record['editable']): ?>
                        <a class="btn btn--ghost" href="<?= e($reliefUrl . '/' . $record['id']) ?>/edit">Edit</a>
                        <form method="post" action="<?= e($reliefUrl . '/' . $record['id']) ?>/delete"
                            data-confirm="Delete this relief record? This cannot be undone." novalidate>
                            <?= csrf_field() ?>
                            <button class="btn btn--ghost u-text-error" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($page > 1 || $hasNextPage): ?>
            <div class="actions">
                <?php if ($page > 1): ?>
                    <a class="btn btn--ghost" href="<?= base_url() ?>/moderator/disasters?page=<?= e((string) ($page - 1)) ?>">Previous</a>
                <?php endif; ?>
                <?php if ($hasNextPage): ?>
                    <a class="btn btn--ghost" href="<?= base_url() ?>/moderator/disasters?page=<?= e((string) ($page + 1)) ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($disaster !== null): ?>
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
                    <div data-demo-form>
                        <p class="demo-note">Preview only. Saving is not available yet.</p>
                        <button class="btn btn--primary" type="submit" disabled>Vouch</button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($reports !== []): ?>
    <section class="section">
        <div class="section__head">
            <h2 class="section__title">Relief reports</h2>
        </div>

        <ul class="row-list">
            <?php foreach ($reports as $report): ?>
                <li class="list-row">
                    <div class="list-row__body">
                        <span class="list-row__title">
                            <?= e($report['title']) ?>
                            <?php if ($report['active']): ?>
                                <span class="badge badge--error">Active</span>
                            <?php endif; ?>
                        </span>
                        <span class="list-row__meta"><?= e($report['period']) ?></span>
                    </div>
                    <a class="btn btn--ghost" href="<?= base_url() ?>/moderator/disasters/<?= e((string) $report['id']) ?>/report">View report</a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel__head">
        <div class="media__body">
            <h2 class="panel__title">Report a disaster</h2>
            <p class="panel__note">
                Tell the Admin about a flood, landslide or fire in your division so they can activate Disaster Mode.
            </p>
        </div>
        <div class="actions panel__actions">
            <span class="preview-action"><button type="button" disabled class="btn btn--ghost">Report disaster</button><span class="demo-note">Not available in this demo</span></span>
        </div>
    </div>
</section>

<?php $pageScripts = ['confirm.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
