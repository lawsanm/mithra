<?php

declare(strict_types=1);

/**
 * Verification detail — one applicant's file, and the moderator's decision on
 * it. Approve and Reject are two POST forms carrying a CSRF token, so the
 * screen works without JavaScript (§7.3, §11).
 *
 * Proof documents and "request more info" are in Figma and not in the
 * database: registration collects no uploads yet, and user_divisions has no
 * state between pending and decided. Both stay visibly unavailable rather than
 * pretending to save. The checklist is the moderator's own aid and is not
 * recorded, so it is not rendered as a form field.
 *
 * @var array $applicant initials, name, status, status_label, submitted
 * @var array $facts     label/value pairs describing the application
 * @var bool  $decided   whether this application has already been decided
 * @var int   $recordId  the membership id the decisions post to
 * @var array|null $flash
 */

$applicant = ($applicant ?? []) + [
    'initials'     => '?',
    'name'         => 'Verification',
    'status'       => 'info',
    'status_label' => 'Unknown',
    'submitted'    => '',
];
$facts     = $facts ?? [];
$decided   = $decided ?? true;
$recordId  = (int) ($recordId ?? 0);

$pageTitle = (string) $applicant['name'];
$navActive = 'verifications';

include __DIR__ . '/../../../partials/header-moderator.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link link" href="<?= base_url() ?>/moderator/verifications">Verifications</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e((string) $applicant['name']) ?></span>
</nav>

<header class="record-head">
    <span class="avatar avatar--lg"><?= e((string) $applicant['initials']) ?></span>
    <h1 class="record-head__title"><?= e((string) $applicant['name']) ?></h1>
    <span class="badge badge--<?= e((string) $applicant['status']) ?>"><?= e((string) $applicant['status_label']) ?></span>
</header>

<p class="record-meta"><?= e((string) $applicant['submitted']) ?></p>

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<div class="stack stack--loose" id="decision">

    <div class="two-col two-col--wide-main">
        <div class="stack">

            <section class="panel">
                <h2 class="panel__title">Application details</h2>
                <div class="facts">
                    <?php foreach ($facts as $fact): ?>
                        <span class="fact">
                            <span class="fact__label"><?= e($fact['label']) ?></span>
                            <span class="fact__value"><?= e($fact['value']) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="panel">
                <h2 class="panel__title">Submitted proof</h2>
                <p class="demo-note">
                    Registration does not collect NIC or address photographs yet. Check these
                    details against the division register before approving.
                </p>
            </section>

        </div>

        <div class="stack">

            <section class="panel">
                <h2 class="panel__title">Before you approve</h2>
                <ul class="checklist">
                    <li><span class="checklist__label">The NIC matches the name on the application.</span></li>
                    <li><span class="checklist__label">The address is inside this GN division.</span></li>
                    <li><span class="checklist__label">Somebody in the division knows this person.</span></li>
                </ul>
                <p class="demo-note">Approving opens the account and lets this member sign in.</p>
            </section>

        </div>
    </div>

    <?php if ($decided): ?>
        <p class="notice notice--info">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            This application has been decided. Nothing further is needed here.
        </p>
    <?php else: ?>
        <div class="actions">
            <form method="post" action="<?= base_url() ?>/moderator/verifications/<?= e((string) $recordId) ?>/reject">
                <?= csrf_field() ?>
                <button class="btn btn--ghost" type="submit">Reject</button>
            </form>

            <span class="preview-action">
                <button class="btn btn--ghost" type="button" disabled>Request more info</button>
                <span class="demo-note">Not available yet</span>
            </span>

            <form method="post" action="<?= base_url() ?>/moderator/verifications/<?= e((string) $recordId) ?>/approve">
                <?= csrf_field() ?>
                <button class="btn btn--primary" type="submit">Approve membership</button>
            </form>
        </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
