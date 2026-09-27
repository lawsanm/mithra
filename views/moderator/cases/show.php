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
 * @var int    $id       the case number from the URL
 * @var array  $case     title, status_label, meta, moderator_is_party, escalated
 * @var array  $parties  rows: initials, name, meta
 * @var string $report   the damage report and counter-statement
 * @var array  $evidence photo groups: label, count
 * @var array  $timeline events: title, time
 * @var array  $signoffs rows: name, status, status_label
 */

$severityLegend = 'Severity reference:  Minor = cosmetic  ·  Moderate = works, needs repair  ·  '
                . 'Major = unusable, repairable  ·  Total loss = beyond repair';

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

$chrome = 'moderator';
include __DIR__ . '/../../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link link" href="<?= base_url() ?>/moderator/cases">Damage cases</a>
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

<div class="stack stack--loose" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>
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
                 disabled></textarea>
            </div>

        </div>
    </div>

    <div class="actions">
        <button class="btn btn--ghost" type="submit" name="action" value="escalate" disabled><?= e($escalateLabel) ?></button>
        <button class="btn btn--ghost" type="submit" name="action" value="request-info" disabled>Request more info</button>
        <button class="btn btn--primary" type="submit" name="action" value="resolve" disabled>Record resolution</button>
    </div>
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
