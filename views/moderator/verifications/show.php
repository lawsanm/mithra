<?php

declare(strict_types=1);

/**
 * Verification detail — one applicant's file, and the moderator's decision on
 * it. The checklist, notes and the three decisions are one POST form, so the
 * screen works without JavaScript and carries a CSRF token (§7.3, §11).
 *
 * @var array $applicant initials, name, status, status_label, submitted
 * @var array $facts     label/value pairs describing the application
 * @var array $proofs    submitted documents: title, meta
 * @var array $checklist review items: id, label, checked
 */

// Sample view data — replaced by the controller once ModerationController lands.
$applicants = [
    'akalvily-a' => [
        'applicant' => [
            'initials'     => 'AA',
            'name'         => 'A. Akalvily',
            'status'       => 'info',
            'status_label' => 'New member',
            'submitted'    => 'New member application  ·  applied 15 Jul 2026',
        ],
        'facts' => [
            ['label' => 'GN division',     'value' => 'Wellawatte'],
            ['label' => 'Home division',   'value' => 'Wellawatte'],
            ['label' => 'Membership type', 'value' => 'Standard'],
            ['label' => 'Referred by',     'value' => '—'],
            ['label' => 'Contact',         'value' => '077 xxx xx05'],
        ],
        'proofs' => [
            ['title' => 'National ID', 'meta' => 'Front and back scan'],
        ],
        'checklist' => [
            ['id' => 'id-match',      'label' => 'ID photo matches submitted documents', 'checked' => false],
            ['id' => 'address-match', 'label' => 'Address matches GN division on file',  'checked' => false],
            ['id' => 'referrer',      'label' => 'Referrer contacted for confirmation',  'checked' => false],
        ],
    ],
    'lawsan-m' => [
        'applicant' => [
            'initials'     => 'ML',
            'name'         => 'M. Lawsan',
            'status'       => 'warning',
            'status_label' => 'Pending review',
            'submitted'    => 'Temporary membership proof  ·  submitted 16 Jul 2026',
        ],
        'facts' => [
            ['label' => 'GN division',     'value' => 'Dehiwala (temporary)'],
            ['label' => 'Home division',   'value' => 'Kollupitiya'],
            ['label' => 'Membership type', 'value' => 'Temporary · 6 months'],
            ['label' => 'Referred by',     'value' => 'T.H.K. Madushan'],
            ['label' => 'Contact',         'value' => '077 xxx xx12'],
        ],
        'proofs' => [
            ['title' => 'National ID',        'meta' => 'Front and back scan'],
            ['title' => 'Proof of residence', 'meta' => 'Utility bill · dated 3 Jul 2026'],
        ],
        'checklist' => [
            ['id' => 'id-match',      'label' => 'ID photo matches submitted documents', 'checked' => true],
            ['id' => 'address-match', 'label' => 'Address matches GN division on file',  'checked' => true],
            ['id' => 'referrer',      'label' => 'Referrer contacted for confirmation',  'checked' => false],
        ],
    ],
    'perera-s' => [
        'applicant' => [
            'initials'     => 'SP',
            'name'         => 'S. Perera',
            'status'       => 'warning',
            'status_label' => 'Pending review',
            'submitted'    => 'NIC + proof of address  ·  submitted 2 days ago',
        ],
        'facts' => [
            ['label' => 'GN division',     'value' => 'Kollupitiya'],
            ['label' => 'Home division',   'value' => 'Kollupitiya'],
            ['label' => 'Membership type', 'value' => 'Standard'],
            ['label' => 'Referred by',     'value' => '—'],
            ['label' => 'Contact',         'value' => '077 xxx xx01'],
        ],
        'proofs' => [
            ['title' => 'National ID',        'meta' => 'Front and back scan'],
            ['title' => 'Proof of residence', 'meta' => 'Utility bill'],
        ],
        'checklist' => [
            ['id' => 'id-match',      'label' => 'ID photo matches submitted documents', 'checked' => true],
            ['id' => 'address-match', 'label' => 'Address matches GN division on file',  'checked' => false],
            ['id' => 'referrer',      'label' => 'Referrer contacted for confirmation',  'checked' => false],
        ],
    ],
    'gunawardena-m' => [
        'applicant' => [
            'initials'     => 'MG',
            'name'         => 'M. Gunawardena',
            'status'       => 'warning',
            'status_label' => 'Pending review',
            'submitted'    => 'NIC + utility bill  ·  submitted 3 days ago',
        ],
        'facts' => [
            ['label' => 'GN division',     'value' => 'Kollupitiya'],
            ['label' => 'Home division',   'value' => 'Kollupitiya'],
            ['label' => 'Membership type', 'value' => 'Standard'],
            ['label' => 'Referred by',     'value' => '—'],
            ['label' => 'Contact',         'value' => '077 xxx xx02'],
        ],
        'proofs' => [
            ['title' => 'National ID',        'meta' => 'Front and back scan'],
            ['title' => 'Proof of residence', 'meta' => 'Utility bill'],
        ],
        'checklist' => [
            ['id' => 'id-match',      'label' => 'ID photo matches submitted documents', 'checked' => true],
            ['id' => 'address-match', 'label' => 'Address matches GN division on file',  'checked' => true],
            ['id' => 'referrer',      'label' => 'Referrer contacted for confirmation',  'checked' => false],
        ],
    ],
    'nizam-a' => [
        'applicant' => [
            'initials'     => 'AN',
            'name'         => 'A. Nizam',
            'status'       => 'warning',
            'status_label' => 'Pending review',
            'submitted'    => 'NIC + GN letter  ·  submitted 4 days ago',
        ],
        'facts' => [
            ['label' => 'GN division',     'value' => 'Kollupitiya'],
            ['label' => 'Home division',   'value' => 'Kollupitiya'],
            ['label' => 'Membership type', 'value' => 'Standard'],
            ['label' => 'Referred by',     'value' => '—'],
            ['label' => 'Contact',         'value' => '077 xxx xx03'],
        ],
        'proofs' => [
            ['title' => 'National ID', 'meta' => 'Front and back scan'],
            ['title' => 'GN letter',   'meta' => 'Signed and stamped'],
        ],
        'checklist' => [
            ['id' => 'id-match',      'label' => 'ID photo matches submitted documents', 'checked' => true],
            ['id' => 'address-match', 'label' => 'Address matches GN division on file',  'checked' => true],
            ['id' => 'referrer',      'label' => 'Referrer contacted for confirmation',  'checked' => false],
        ],
    ],
    'wickrama-t' => [
        'applicant' => [
            'initials'     => 'TW',
            'name'         => 'T. Wickrama',
            'status'       => 'info',
            'status_label' => 'Needs more info',
            'submitted'    => 'NIC only — address proof missing  ·  submitted 5 days ago',
        ],
        'facts' => [
            ['label' => 'GN division',     'value' => '—'],
            ['label' => 'Home division',   'value' => '—'],
            ['label' => 'Membership type', 'value' => 'Standard'],
            ['label' => 'Referred by',     'value' => '—'],
            ['label' => 'Contact',         'value' => '077 xxx xx04'],
        ],
        'proofs' => [
            ['title' => 'National ID', 'meta' => 'Front and back scan'],
        ],
        'checklist' => [
            ['id' => 'id-match',      'label' => 'ID photo matches submitted documents', 'checked' => true],
            ['id' => 'address-match', 'label' => 'Address matches GN division on file',  'checked' => false],
            ['id' => 'referrer',      'label' => 'Referrer contacted for confirmation',  'checked' => false],
        ],
    ],
    'rajapaksa-d' => [
        'applicant' => [
            'initials'     => 'DR',
            'name'         => 'D. Rajapaksa',
            'status'       => 'error',
            'status_label' => 'Rejected',
            'submitted'    => 'NIC + proof of address  ·  rejected 11 Jul 2026',
        ],
        'facts' => [
            ['label' => 'GN division',     'value' => 'Bambalapitiya'],
            ['label' => 'Home division',   'value' => 'Bambalapitiya'],
            ['label' => 'Membership type', 'value' => 'Standard'],
            ['label' => 'Referred by',     'value' => '—'],
            ['label' => 'Contact',         'value' => '077 xxx xx06'],
        ],
        'proofs' => [
            ['title' => 'National ID',        'meta' => 'Front and back scan'],
            ['title' => 'Proof of residence', 'meta' => 'Utility bill · address outside this division'],
        ],
        'checklist' => [
            ['id' => 'id-match',      'label' => 'ID photo matches submitted documents', 'checked' => true],
            ['id' => 'address-match', 'label' => 'Address matches GN division on file',  'checked' => false],
            ['id' => 'referrer',      'label' => 'Referrer contacted for confirmation',  'checked' => false],
        ],
    ],
];

$verificationId = (string) ($_GET['id'] ?? '');
$sample         = $applicants[$verificationId] ?? reset($applicants);

$applicant ??= $sample['applicant'];
$facts     ??= $sample['facts'];
$proofs    ??= $sample['proofs'];
$checklist ??= $sample['checklist'];

$pageTitle = $applicant['name'];
$navActive = 'verifications';

include __DIR__ . '/../../../partials/header-moderator.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link link" href="/moderator/verifications">Verifications</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($applicant['name']) ?></span>
</nav>

<header class="record-head">
    <span class="avatar avatar--lg"><?= e($applicant['initials']) ?></span>
    <h1 class="record-head__title"><?= e($applicant['name']) ?></h1>
    <span class="badge badge--<?= e($applicant['status']) ?>"><?= e($applicant['status_label']) ?></span>
</header>

<p class="record-meta"><?= e($applicant['submitted']) ?></p>

<form class="stack stack--loose" method="post" action="/moderator/verifications/<?= rawurlencode($verificationId) ?>/decision">
    <?= csrf_field() ?>

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
                <?php foreach ($proofs as $proof): ?>
                    <div class="media">
                        <span class="thumb thumb--photo">Photo</span>
                        <span class="media__body">
                            <span class="media__title media__title--sm"><?= e($proof['title']) ?></span>
                            <span class="media__meta"><?= e($proof['meta']) ?></span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </section>

        </div>

        <div class="stack">

            <section class="panel">
                <h2 class="panel__title">Verification checklist</h2>
                <ul class="checklist">
                    <?php foreach ($checklist as $check): ?>
                        <li>
                            <label class="checklist__item" for="check-<?= e($check['id']) ?>">
                                <input
                                    class="checklist__input"
                                    type="checkbox"
                                    id="check-<?= e($check['id']) ?>"
                                    name="checks[]"
                                    value="<?= e($check['id']) ?>"
                                    <?= $check['checked'] ? 'checked' : '' ?>
                                >
                                <span class="checklist__label"><?= e($check['label']) ?></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <div class="field">
                <label class="field__label" for="verification-notes">Notes (optional)</label>
                <textarea
                    class="textarea"
                    id="verification-notes"
                    name="notes"
                    placeholder="Add any notes for the record"
                ></textarea>
            </div>

        </div>
    </div>

    <div class="actions">
        <button class="btn btn--ghost" type="submit" name="decision" value="reject">Reject</button>
        <button class="btn btn--ghost" type="submit" name="decision" value="request-info">Request more info</button>
        <button class="btn btn--primary" type="submit" name="decision" value="approve">Approve membership</button>
    </div>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
