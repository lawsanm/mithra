<?php

declare(strict_types=1);

/**
 * Verify a disaster contribution (Plan §14.3). The Liaison sees the sponsor's
 * account and the Moderator's acknowledgement side by side, plus the relief
 * the Moderator recorded handing out from it, and then verifies, queries or
 * rejects. A verified record is locked and flows into the sponsor's CSR report.
 *
 * @var int   $id            contribution id from the URL
 * @var array $contribution  reference, sponsor, disaster, division, moderator, status, status_label, meta, editable
 * @var array $comparison    rows: label, sponsor, moderator, match (bool)
 * @var array $evidence      two groups: label, files (name, meta)
 * @var array $reliefRecords rows: title, meta, value
 * @var string $reliefTotal  total value of linked relief records
 * @var array $checks        checklist lines: label, checked
 * @var array $log           timeline rows: title, date
 * @var array $draft         verified_value, note
 */

$baseUrl = base_url() . '/sponsor-liaison/disasters/contributions';
$showUrl = $baseUrl . '/' . rawurlencode((string) $id);

$pageTitle = $contribution['reference'] . ' — verify contribution';
$navActive = 'disasters';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/disasters">Disasters</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <a class="breadcrumb__link" href="<?= e($baseUrl) ?>">Contributions</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($contribution['reference']) ?></span>
</nav>

<header class="record-head">
    <h1 class="record-head__title"><?= e($contribution['sponsor']) ?> · <?= e($contribution['disaster']) ?></h1>
    <span class="badge badge--<?= e($contribution['status']) ?>"><?= e($contribution['status_label']) ?></span>
</header>
<p class="record-meta"><?= e($contribution['reference']) ?>  ·  <?= e($contribution['meta']) ?></p>

<div class="two-col">
    <div class="stack stack--loose">

        <section class="form-card form-card--wide">
            <h2 class="form-card__legend form-card__legend--lg">Both accounts</h2>
            <div class="scroll-x">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Sponsor says</th>
                            <th><?= e($contribution['moderator']) ?> confirms</th>
                            <th>Match</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparison as $line): ?>
                            <tr>
                                <th scope="row"><?= e($line['label']) ?></th>
                                <td><?= e($line['sponsor']) ?></td>
                                <td><?= e($line['moderator']) ?></td>
                                <td>
                                    <?php if ($line['match']): ?>
                                        <span class="badge badge--success">Agrees</span>
                                    <?php else: ?>
                                        <span class="badge badge--error">Differs</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="form-card form-card--wide">
            <h2 class="form-card__legend form-card__legend--lg">Receipts from both parties</h2>
            <?php foreach ($evidence as $group): ?>
                <div class="photo-group">
                    <span class="photo-group__label"><?= e($group['label']) ?></span>
                    <ul class="row-list">
                        <?php foreach ($group['files'] as $file): ?>
                            <li class="list-row">
                                <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-package"></use></svg>
                                <div class="list-row__body">
                                    <span class="list-row__title"><?= e($file['name']) ?></span>
                                    <span class="list-row__meta"><?= e($file['meta']) ?></span>
                                </div>
                                <span class="preview-action"><button class="btn btn--ghost" type="button" disabled>View</button></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="form-card form-card--wide">
            <h2 class="form-card__legend form-card__legend--lg">Relief handed out from it</h2>
            <p class="page-intro__meta">Relief records the Moderator linked to this contribution.</p>
            <ul class="row-list">
                <?php foreach ($reliefRecords as $record): ?>
                    <li class="list-row">
                        <div class="list-row__body">
                            <span class="list-row__title"><?= e($record['title']) ?></span>
                            <span class="list-row__meta"><?= e($record['meta']) ?></span>
                        </div>
                        <strong class="list-row__amount"><?= e($record['value']) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="list-row__meta">Total: <?= e($reliefTotal) ?></p>
        </section>

        <section class="form-card form-card--wide">
            <h2 class="form-card__legend form-card__legend--lg">History</h2>
            <div class="timeline">
                <?php foreach ($log as $entry): ?>
                    <div class="timeline__item">
                        <p class="timeline__title"><?= e($entry['title']) ?></p>
                        <span class="timeline__date"><?= e($entry['date']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="stack">
        <div class="form-card form-card--wide" data-demo-form>
            <p class="demo-note">Preview only. Saving is not available yet.</p>
            <h2 class="form-card__legend form-card__legend--lg">Your verification</h2>

            <ul class="checklist">
                <?php foreach ($checks as $index => $check): ?>
                    <li>
                        <label class="cluster">
                            <input type="checkbox" name="checks[]" value="<?= e((string) $index) ?>"<?= $check['checked'] ? ' checked' : '' ?> disabled>
                            <span class="checklist__label"><?= e($check['label']) ?></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="field">
                <label class="field__label" for="verified-value">Verified value (LKR)</label>
                <input class="input" type="number" id="verified-value" name="verified_value" value="<?= e($draft['verified_value']) ?>" disabled>
                <span class="field__hint">This figure appears in the sponsor's CSR report.</span>
            </div>

            <div class="field">
                <label class="field__label" for="note">Note (required to query or reject)</label>
                <textarea class="textarea" id="note" name="note" rows="3" placeholder="What does not match, and who needs to answer…" disabled><?= e($draft['note']) ?></textarea>
            </div>

            <div class="actions">
                <button class="btn btn--primary" type="submit" name="decision" value="verify" disabled>Verify &amp; record</button>
            </div>
            <div class="actions">
                <button class="btn btn--ghost" type="submit" name="decision" value="query_sponsor" disabled>Query sponsor</button>
                <button class="btn btn--ghost" type="submit" name="decision" value="query_moderator" disabled>Query Moderator</button>
                <button class="btn btn--ghost u-text-error" type="submit" name="decision" value="reject" disabled>Reject</button>
            </div>
        </div>

        <?php if ($contribution['editable']): ?>
            <div class="form-card form-card--wide" data-demo-form>
                <p class="demo-note">Preview only. Deleting is not available yet.</p>
                <h2 class="form-card__legend">Not verified yet</h2>
                <p class="page-intro__meta">You can correct or withdraw this record until you verify it.</p>
                <div class="actions">
                    <a class="btn btn--ghost" href="<?= e($showUrl) ?>/edit">Edit</a>
                    <button class="btn btn--ghost u-text-error" type="submit" disabled>Delete</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="notice notice--info notice--full">
    Verifying locks this record and adds it to <?= e($contribution['sponsor']) ?>'s CSR report and the
    transparency records. No points move. You verify what happened; you do not approve the relief itself.
</div>

<?php include __DIR__ . '/../../../../partials/footer.php'; ?>
