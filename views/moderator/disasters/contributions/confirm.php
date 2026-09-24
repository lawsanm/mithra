<?php

declare(strict_types=1);

/**
 * Moderator confirms (or disputes) a sponsor's Disaster Mode contribution
 * (Plan §14.3). This is the second receipt the Liaison verifies against:
 * what actually arrived, when, the acknowledgement the Moderator gave the
 * sponsor, and which relief records it paid for.
 *
 * @var int   $id            contribution id from the URL
 * @var array $claim         reference, sponsor, kind, description, value, date, receipt
 * @var array $reliefRecords checkbox options: id, title, checked
 * @var array $draft         outcome, received_description, received_value, received_on, ack_reference, note
 */

$pageTitle = 'Confirm ' . $claim['reference'];
$navActive = 'disasters';

$chrome = 'moderator';
include __DIR__ . '/../../../../partials/header.php';

$outcomes = [
    'received' => ['Received as described',       'Everything the sponsor lists arrived.'],
    'partial'  => ['Received, but different',     'Less, more or something else arrived — say what below.'],
    'none'     => ['Not received',                'Nothing from this sponsor reached you.'],
];

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/moderator/disasters">Disaster relief</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <a class="breadcrumb__link" href="<?= base_url() ?>/moderator/disasters/contributions">Sponsor contributions</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($claim['reference']) ?></span>
</nav>

<h1 class="detail__title">Confirm what you received from <?= e($claim['sponsor']) ?></h1>

<section class="panel">
    <h2 class="panel__title">The sponsor's account</h2>
    <div class="facts facts--wide">
        <span class="fact"><span class="fact__label">Kind</span><span class="fact__value"><?= e($claim['kind']) ?></span></span>
        <span class="fact"><span class="fact__label">What</span><span class="fact__value"><?= e($claim['description']) ?></span></span>
        <span class="fact"><span class="fact__label">Value</span><span class="fact__value"><?= e($claim['value']) ?></span></span>
        <span class="fact"><span class="fact__label">Handed over</span><span class="fact__value"><?= e($claim['date']) ?></span></span>
        <span class="fact"><span class="fact__label">Sponsor's receipt</span><span class="fact__value"><?= e($claim['receipt']) ?></span></span>
    </div>
</section>

<div class="form-card" data-demo-form>
    <p class="demo-note">Preview only. Saving is not available yet.</p>

    <p class="form-card__legend">What happened</p>
    <?php foreach ($outcomes as $value => [$title, $note]): ?>
        <label class="choice">
            <input class="choice__input" type="radio" name="outcome" value="<?= e($value) ?>"<?= $draft['outcome'] === $value ? ' checked' : '' ?> disabled>
            <span class="choice__body">
                <span class="choice__title"><?= e($title) ?></span>
                <span class="choice__note"><?= e($note) ?></span>
            </span>
        </label>
    <?php endforeach; ?>

    <div class="field">
        <label class="field__label" for="received-description">What you received</label>
        <input class="input" type="text" id="received-description" name="received_description" value="<?= e($draft['received_description']) ?>" disabled>
    </div>

    <div class="field-row">
        <div class="field">
            <label class="field__label" for="received-value">Cash received (LKR)</label>
            <input class="input input--half" type="number" id="received-value" name="received_value" value="<?= e($draft['received_value']) ?>" placeholder="Only for cash" disabled>
        </div>
        <div class="field">
            <label class="field__label" for="received-on">Date received</label>
            <input class="input input--date" type="date" id="received-on" name="received_on" value="<?= e($draft['received_on']) ?>" disabled>
        </div>
    </div>

    <div class="field">
        <label class="field__label" for="ack-reference">Your acknowledgement number</label>
        <input class="input input--half" type="text" id="ack-reference" name="ack_reference" value="<?= e($draft['ack_reference']) ?>" placeholder="ACK-KOL-015" disabled>
        <span class="field__hint">The number on the acknowledgement slip you signed and gave the sponsor.</span>
    </div>

    <label class="upload-drop">
        <span class="upload-drop__glyph" aria-hidden="true">＋</span>
        <span>Upload your signed acknowledgement and a photo of what arrived — up to 3 files</span>
        <input class="visually-hidden" type="file" name="moderator_proof[]" accept="image/*,application/pdf" multiple disabled>
    </label>

    <p class="form-card__legend">Relief it paid for</p>
    <ul class="checklist">
        <?php foreach ($reliefRecords as $record): ?>
            <li>
                <label class="cluster">
                    <input type="checkbox" name="relief_record_ids[]" value="<?= e((string) $record['id']) ?>"<?= $record['checked'] ? ' checked' : '' ?> disabled>
                    <span class="checklist__label"><?= e($record['title']) ?></span>
                </label>
            </li>
        <?php endforeach; ?>
    </ul>
    <span class="field__hint">Pick the relief records you handed out from this contribution. You can link more later.</span>

    <div class="field">
        <label class="field__label" for="note">Note for the Liaison (required if different or not received)</label>
        <textarea class="textarea" id="note" name="note" rows="3" disabled><?= e($draft['note']) ?></textarea>
    </div>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/moderator/disasters/contributions">Cancel</a>
        <button class="btn btn--primary" type="submit" disabled>Send confirmation</button>
    </div>
</div>

<?php include __DIR__ . '/../../../../partials/footer.php'; ?>
