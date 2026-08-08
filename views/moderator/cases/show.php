<?php

declare(strict_types=1);

/**
 * Damage case detail — the moderator mediating one case between lender and
 * borrower.
 *
 * Two variants, per the design: when the moderator is themselves a party to the
 * case it is a conflict of interest, so the status badge carries a "!" flag and
 * "Escalate to Admin" is enabled; otherwise they mediate it directly and
 * escalation stays disabled. A case already escalated shows that as its own
 * settled state regardless of the above.
 *
 * @var array  $case     title, status_label, meta, moderator_is_party, escalated
 * @var array  $parties  rows: initials, name, meta
 * @var string $report   the damage report and counter-statement
 * @var array  $evidence photo groups: label, count
 * @var array  $timeline events: title, time
 * @var array  $signoffs rows: name, status, status_label
 */

// Sample view data — replaced by the controller once ModerationController lands.
$severityLegend = 'Severity reference:  Minor = cosmetic  ·  Moderate = works, needs repair  ·  '
                . 'Major = unusable, repairable  ·  Total loss = beyond repair';

$sampleCases = [
    'grinding-drill' => [
        'case' => [
            'title'              => 'Grinding Drill',
            'status_label'       => 'Mediating',
            'meta'               => 'Case #CD-0142  ·  damage reported 14 Jul 2026  ·  in-person mediation required',
            'moderator_is_party' => true,
            'escalated'          => false,
        ],
        'parties' => [
            ['initials' => 'TM', 'name' => 'T.H.K. Madushan', 'meta' => 'Lender · reported the damage · Trust 96'],
            ['initials' => 'JK', 'name' => 'J. Kavipriya',    'meta' => 'Borrower · returned 13 Jul · Trust 96'],
        ],
        'report' => 'Owner states the chuck is cracked and the battery no longer holds charge. '
                  . 'Borrower’s counter-statement says the crack was present at handover — see baseline photos.',
        'evidence' => [
            ['label' => 'Handover baseline  ·  8 Jul', 'count' => 2],
            ['label' => 'Return  ·  13 Jul',           'count' => 2],
        ],
        'timeline' => [
            ['title' => 'Damage reported by T.H.K. Madushan',        'time' => '14 Jul, 9:20 AM'],
            ['title' => 'Borrower responded with counter-statement', 'time' => '14 Jul, 4:05 PM'],
            ['title' => 'Assigned to moderator for mediation',       'time' => '15 Jul, 10:00 AM'],
        ],
        'signoffs' => [
            ['name' => 'Moderator — J. Kavipriya', 'status' => 'success', 'status_label' => 'Signed'],
            ['name' => 'Lender — T.H.K. Madushan', 'status' => 'warning', 'status_label' => 'Pending'],
            ['name' => 'Borrower — J. Kavipriya',  'status' => 'warning', 'status_label' => 'Pending'],
        ],
    ],
    'camping-tent-4p' => [
        'case' => [
            'title'              => 'Camping Tent (4-person)',
            'status_label'       => 'Awaiting sign-off',
            'meta'               => 'Case #CD-0138  ·  damage reported 10 Jul 2026  ·  repair confirmed, awaiting sign-off',
            'moderator_is_party' => true,
            'escalated'          => false,
        ],
        'parties' => [
            ['initials' => 'ML', 'name' => 'M. Lawsan',   'meta' => 'Lender · reported the damage · Trust 88'],
            ['initials' => 'JK', 'name' => 'J. Kavipriya', 'meta' => 'Borrower · returned 9 Jul · Trust 96'],
        ],
        'report' => 'Lender reports a bent pole and a torn guy-line on return. Borrower had the pole '
                  . 'repaired and the tear patched before returning; repair photos attached alongside '
                  . 'the original condition photos.',
        'evidence' => [
            ['label' => 'Handover baseline  ·  3 Jul', 'count' => 2],
            ['label' => 'Return  ·  9 Jul',            'count' => 2],
        ],
        'timeline' => [
            ['title' => 'Damage reported by M. Lawsan',        'time' => '9 Jul, 6:40 PM'],
            ['title' => 'Borrower shared repair confirmation', 'time' => '10 Jul, 11:15 AM'],
            ['title' => 'Assigned to moderator for mediation', 'time' => '10 Jul, 1:00 PM'],
        ],
        'signoffs' => [
            ['name' => 'Moderator — J. Kavipriya', 'status' => 'success', 'status_label' => 'Signed'],
            ['name' => 'Lender — M. Lawsan',       'status' => 'success', 'status_label' => 'Signed'],
            ['name' => 'Borrower — J. Kavipriya',  'status' => 'warning', 'status_label' => 'Pending'],
        ],
    ],
    'cordless-drill-case' => [
        'case' => [
            'title'              => 'Case #1042 — Cordless Drill',
            'status_label'       => 'Awaiting meeting',
            'meta'               => 'Case #1042  ·  moderate damage reported  ·  meet by 28 Jul 2026',
            'moderator_is_party' => false,
            'escalated'          => false,
        ],
        'parties' => [
            ['initials' => 'RF', 'name' => 'R. Fernando', 'meta' => 'Lender · reported the damage · Trust 91'],
            ['initials' => 'SP', 'name' => 'S. Perera',   'meta' => 'Borrower · returned 24 Jul · Trust 84'],
        ],
        'report' => 'Lender reports the drill’s gearbox is grinding and one battery no longer charges. '
                  . 'In-person meeting requested to inspect the tool before assessing severity.',
        'evidence' => [
            ['label' => 'Handover baseline  ·  18 Jul', 'count' => 2],
            ['label' => 'Return  ·  24 Jul',            'count' => 2],
        ],
        'timeline' => [
            ['title' => 'Damage reported by R. Fernando',      'time' => '24 Jul, 5:10 PM'],
            ['title' => 'Assigned to moderator for mediation', 'time' => '25 Jul, 9:00 AM'],
            ['title' => 'In-person meeting requested',         'time' => '25 Jul, 9:05 AM'],
        ],
        'signoffs' => [
            ['name' => 'Moderator — J. Kavipriya', 'status' => 'warning', 'status_label' => 'Pending'],
            ['name' => 'Lender — R. Fernando',     'status' => 'warning', 'status_label' => 'Pending'],
            ['name' => 'Borrower — S. Perera',     'status' => 'warning', 'status_label' => 'Pending'],
        ],
    ],
    'camping-tent-case' => [
        'case' => [
            'title'              => 'Case #1039 — Camping Tent',
            'status_label'       => 'Awaiting sign-off',
            'meta'               => 'Case #1039  ·  minor damage  ·  2 of 3 signed off',
            'moderator_is_party' => false,
            'escalated'          => false,
        ],
        'parties' => [
            ['initials' => 'AN', 'name' => 'A. Nizam',       'meta' => 'Lender · reported the damage · Trust 89'],
            ['initials' => 'MG', 'name' => 'M. Gunawardena', 'meta' => 'Borrower · returned 21 Jul · Trust 92'],
        ],
        'report' => 'Lender reports a small tear in the tent flysheet. Borrower agrees with the '
                  . 'assessment and a repair cost has been agreed; awaiting the borrower’s final sign-off.',
        'evidence' => [
            ['label' => 'Handover baseline  ·  15 Jul', 'count' => 2],
            ['label' => 'Return  ·  21 Jul',            'count' => 2],
        ],
        'timeline' => [
            ['title' => 'Damage reported by A. Nizam',        'time' => '21 Jul, 3:30 PM'],
            ['title' => 'Repair cost agreed by both parties', 'time' => '22 Jul, 10:20 AM'],
            ['title' => 'Assigned to moderator for sign-off', 'time' => '22 Jul, 10:25 AM'],
        ],
        'signoffs' => [
            ['name' => 'Moderator — J. Kavipriya',  'status' => 'success', 'status_label' => 'Signed'],
            ['name' => 'Lender — A. Nizam',         'status' => 'success', 'status_label' => 'Signed'],
            ['name' => 'Borrower — M. Gunawardena', 'status' => 'warning', 'status_label' => 'Pending'],
        ],
    ],
    'pressure-washer-case' => [
        'case' => [
            'title'              => 'Case #1035 — Pressure Washer',
            'status_label'       => 'Escalated to Admin',
            'meta'               => 'Case #1035  ·  party refused to sign off  ·  escalated to Admin',
            'moderator_is_party' => false,
            'escalated'          => true,
        ],
        'parties' => [
            ['initials' => 'KB', 'name' => 'K. Bandara',  'meta' => 'Lender · reported the damage · Trust 90'],
            ['initials' => 'TW', 'name' => 'T. Wickrama', 'meta' => 'Borrower · returned 16 Jul · Trust 71'],
        ],
        'report' => 'Lender reports the pump housing is cracked and no longer holds pressure. Borrower '
                  . 'disputes the severity and has refused to sign off on the agreed resolution, so the '
                  . 'case has been escalated to an Admin.',
        'evidence' => [
            ['label' => 'Handover baseline  ·  10 Jul', 'count' => 2],
            ['label' => 'Return  ·  16 Jul',            'count' => 2],
        ],
        'timeline' => [
            ['title' => 'Damage reported by K. Bandara', 'time' => '16 Jul, 8:15 AM'],
            ['title' => 'Borrower refused to sign off',  'time' => '17 Jul, 2:40 PM'],
            ['title' => 'Escalated to Admin',            'time' => '17 Jul, 2:45 PM'],
        ],
        'signoffs' => [
            ['name' => 'Moderator — J. Kavipriya', 'status' => 'success', 'status_label' => 'Signed'],
            ['name' => 'Lender — K. Bandara',      'status' => 'success', 'status_label' => 'Signed'],
            ['name' => 'Borrower — T. Wickrama',   'status' => 'error',   'status_label' => 'Refused'],
        ],
    ],
];

$caseId = (string) ($_GET['id'] ?? '');
$sample = $sampleCases[$caseId] ?? reset($sampleCases);

$case     ??= $sample['case'];
$parties  ??= $sample['parties'];
$report   ??= $sample['report'];
$evidence ??= $sample['evidence'];
$timeline ??= $sample['timeline'];
$signoffs ??= $sample['signoffs'];

// A conflict of interest is flagged on the badge and is the only thing that
// lets this moderator hand the case to an Admin.
if ($case['escalated']) {
    $badgeStatus     = 'error';
    $badgeLabel      = $case['status_label'];
    $escalateLabel   = 'Escalated to Admin';
    $canEscalate     = false;
} elseif ($case['moderator_is_party']) {
    $badgeStatus     = 'warning';
    $badgeLabel      = '! ' . $case['status_label'];
    $escalateLabel   = 'Escalate to Admin';
    $canEscalate     = true;
} else {
    $badgeStatus     = 'warning';
    $badgeLabel      = $case['status_label'];
    $escalateLabel   = 'Escalate to Admin';
    $canEscalate     = false;
}

$pageTitle = $case['title'];
$navActive = 'cases';

include __DIR__ . '/../../../partials/header-moderator.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link link" href="/moderator/cases">Damage cases</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($case['title']) ?></span>
</nav>

<header class="record-head">
    <h1 class="record-head__title"><?= e($case['title']) ?></h1>
    <span class="badge badge--<?= e($badgeStatus) ?>"><?= e($badgeLabel) ?></span>
</header>

<p class="record-meta"><?= e($case['meta']) ?></p>

<?php if ($case['moderator_is_party'] && !$case['escalated']): ?>
    <p class="notice notice--warning">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-alert-triangle"></use></svg>
        You are a party to this case, so you cannot mediate it. Hand it to an Admin before it goes further.
    </p>
<?php endif; ?>

<form class="stack stack--loose" method="post" action="/moderator/cases/<?= rawurlencode($caseId) ?>/resolution">
    <?= csrf_field() ?>

    <div class="two-col two-col--wide-main">
        <div class="stack">

            <section class="panel">
                <h2 class="panel__title">Parties</h2>
                <?php foreach ($parties as $party): ?>
                    <div class="media">
                        <span class="avatar avatar--md"><?= e($party['initials']) ?></span>
                        <span class="media__body">
                            <span class="media__title media__title--sm"><?= e($party['name']) ?></span>
                            <span class="media__meta"><?= e($party['meta']) ?></span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="panel">
                <h2 class="panel__title">Damage report &amp; evidence</h2>
                <p class="panel__prose"><?= e($report) ?></p>

                <div class="panel-row">
                    <?php foreach ($evidence as $group): ?>
                        <div class="photo-group">
                            <span class="photo-group__label"><?= e($group['label']) ?></span>
                            <div class="photo-grid">
                                <?php for ($photo = 1; $photo <= $group['count']; $photo++): ?>
                                    <span class="thumb thumb--photo">Photo</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="panel__note"><?= e($severityLegend) ?></p>
            </section>

        </div>

        <div class="stack">

            <section class="panel">
                <h2 class="panel__title">Case timeline</h2>
                <div class="timeline">
                    <?php foreach ($timeline as $event): ?>
                        <div class="timeline__item">
                            <p class="timeline__title"><?= e($event['title']) ?></p>
                            <span class="timeline__date"><?= e($event['time']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="panel">
                <h2 class="panel__title">Three-party sign-off</h2>
                <?php foreach ($signoffs as $signoff): ?>
                    <div class="setting-row">
                        <span class="setting-row__body">
                            <span class="setting-row__title"><?= e($signoff['name']) ?></span>
                        </span>
                        <span class="badge badge--<?= e($signoff['status']) ?>"><?= e($signoff['status_label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </section>

            <div class="field">
                <label class="field__label" for="mediation-decision">Mediation decision</label>
                <textarea
                    class="textarea"
                    id="mediation-decision"
                    name="decision_notes"
                    placeholder="Record the agreed resolution and any points awarded…"
                    required
                ></textarea>
            </div>

        </div>
    </div>

    <div class="actions">
        <button class="btn btn--ghost" type="submit" name="action" value="escalate" formnovalidate <?= $canEscalate ? '' : 'disabled' ?>><?= e($escalateLabel) ?></button>
        <button class="btn btn--ghost" type="submit" name="action" value="request-info" formnovalidate>Request more info</button>
        <button class="btn btn--primary" type="submit" name="action" value="resolve">Record resolution</button>
    </div>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
