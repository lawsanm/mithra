<?php

declare(strict_types=1);

/**
 * One sponsor's profile.
 *
 * @var array      $record id, name, account, contact, email, contact_name, contact_phone, points,
 *                         agreement_label, agreement_details, internal_notes, contributions,
 *                         onboarded, active, badge, badge_label
 * @var array|null $flash  result of the last change
 */

$pageTitle = $record['name'];
$navActive = 'sponsors';
$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../partials/header.php';

$profileUrl = base_url() . '/sponsor-liaison/sponsors/' . $record['id'];

?>
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/sponsors">Sponsors</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span aria-current="page"><?= e($record['name']) ?></span>
</nav>

<header class="page-intro">
    <h1 class="page-intro__title">
        <?= e($record['name']) ?>
        <span class="badge badge--<?= e($record['badge']) ?>"><?= e($record['badge_label']) ?></span>
    </h1>
    <div class="actions">
        <a class="btn btn--ghost" href="<?= e($profileUrl) ?>/edit">Edit sponsor</a>
        <?php if ($record['active']): ?>
            <form method="post" action="<?= e($profileUrl) ?>/deactivate"
                data-confirm="Deactivate this sponsor? Its contribution history stays on record." novalidate>
                <?= csrf_field() ?>
                <button class="btn btn--ghost u-text-error" type="submit">Deactivate</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e($profileUrl) ?>/reactivate" novalidate>
                <?= csrf_field() ?>
                <button class="btn btn--ghost" type="submit">Reactivate</button>
            </form>
        <?php endif; ?>
    </div>
</header>

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<section class="panel">
    <h2 class="panel__title">Sponsor details</h2>
    <dl class="facts">
        <div class="fact"><dt class="fact__label">Company</dt><dd class="fact__value"><?= e($record['name']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Sponsor login</dt><dd class="fact__value"><?= e($record['account']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Contact person</dt><dd class="fact__value"><?= e($record['contact_name'] !== '' ? $record['contact_name'] : '—') ?></dd></div>
        <div class="fact"><dt class="fact__label">Contact email</dt><dd class="fact__value"><?= e($record['email'] !== '' ? $record['email'] : '—') ?></dd></div>
        <div class="fact"><dt class="fact__label">Contact phone</dt><dd class="fact__value"><?= e($record['contact_phone'] !== '' ? $record['contact_phone'] : '—') ?></dd></div>
        <div class="fact"><dt class="fact__label">Onboarded</dt><dd class="fact__value"><?= e($record['onboarded']) ?></dd></div>
    </dl>
</section>

<section class="panel">
    <h2 class="panel__title">Agreement and contributions</h2>
    <dl class="facts">
        <div class="fact"><dt class="fact__label">Agreement</dt><dd class="fact__value"><?= e($record['agreement_label']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Agreement details</dt><dd class="fact__value"><?= e($record['agreement_details'] !== '' ? $record['agreement_details'] : '—') ?></dd></div>
        <div class="fact"><dt class="fact__label">Total contribution</dt><dd class="fact__value"><?= e($record['points']) ?></dd></div>
        <div class="fact"><dt class="fact__label">Contributions</dt><dd class="fact__value"><?= e($record['contributions']) ?></dd></div>
    </dl>
</section>

<?php if ($record['internal_notes'] !== ''): ?>
    <section class="panel">
        <h2 class="panel__title">Internal notes</h2>
        <p><?= e($record['internal_notes']) ?></p>
    </section>
<?php endif; ?>

<div class="actions">
    <a class="btn btn--primary" href="<?= base_url() ?>/sponsor-liaison/purchases?sponsor=<?= e(rawurlencode($record['name'])) ?>">View contributions</a>
    <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/sponsors">Back to sponsors</a>
</div>
<?php $pageScripts = ['confirm.js']; ?>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
