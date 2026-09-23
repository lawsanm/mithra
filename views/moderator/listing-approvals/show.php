<?php

declare(strict_types=1);

/**
 * Listing approval detail — one listing, its proof of value, its audit trail,
 * and the reviewer's decision (Plan §9.2): approve, adjust the declared value
 * with a reason the lender sees, or reject with a reason. One POST form with a
 * CSRF token, so it works without JavaScript (§7.3, §11).
 *
 * @var string $chrome      'moderator' or 'admin' — which page chrome to use
 * @var string $basePath    URL of the queue this listing belongs to
 * @var array  $listing     id, title, status, status_label, meta, description,
 *                          declared_value, decided
 * @var array  $facts       label/value pairs describing the listing
 * @var string $requirement the Plan §9.1 proof rule for this declared value
 * @var array  $proofGaps   messages when the proof on file falls short of that rule
 * @var array  $photos      label/url pairs: proof of value first, then listing photos
 * @var array  $trail       audit rows: line, reason
 * @var array  $errors      per-field messages from the last decision attempt
 * @var array  $old         the last decision attempt's values
 * @var array|null $flash
 */

$chrome    = ($chrome ?? 'moderator') === 'admin' ? 'admin' : 'moderator';
$errors    = $errors ?? [];
$old       = $old ?? ['decision' => '', 'declared_value' => '', 'reason' => ''];
$photos    = $photos ?? [];
$trail     = $trail ?? [];
$proofGaps = $proofGaps ?? [];

$pageTitle = $listing['title'];
$navActive = 'listing-approvals';

include __DIR__ . '/../../../partials/header-' . $chrome . '.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link link" href="<?= e($basePath) ?>">Listing approvals</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page"><?= e($listing['title']) ?></span>
</nav>

<header class="record-head">
    <span class="thumb thumb--sm">Photo</span>
    <h1 class="record-head__title"><?= e($listing['title']) ?></h1>
    <span class="badge badge--<?= e($listing['status']) ?>"><?= e($listing['status_label']) ?></span>
</header>

<p class="record-meta"><?= e($listing['meta']) ?></p>

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<?php if (isset($errors['form'])): ?>
    <p class="notice notice--error" role="alert"><?= e($errors['form']) ?></p>
<?php endif; ?>

<div class="stack stack--loose" id="decision">

    <div class="two-col two-col--wide-main">
        <div class="stack">

            <section class="panel">
                <h2 class="panel__title">Listing details</h2>
                <div class="facts">
                    <?php foreach ($facts as $fact): ?>
                        <span class="fact">
                            <span class="fact__label"><?= e($fact['label']) ?></span>
                            <span class="fact__value"><?= e($fact['value']) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php if ($listing['description'] !== ''): ?>
                    <p class="panel__prose"><?= e($listing['description']) ?></p>
                <?php endif; ?>
            </section>

            <section class="panel">
                <h2 class="panel__title">Value proof &amp; condition photos</h2>
                <p class="field__hint"><?= e($requirement) ?></p>
                <?php foreach ($proofGaps as $gap): ?>
                    <p class="notice notice--error"><?= e($gap) ?></p>
                <?php endforeach; ?>
                <div class="photo-grid">
                    <?php foreach ($photos as $photo): ?>
                        <a class="link" href="<?= e($photo['url']) ?>">
                            <img class="thumb thumb--photo thumb__img" src="<?= e($photo['url']) ?>" alt="<?= e($photo['label']) ?>">
                            <?= e($photo['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="panel">
                <h2 class="panel__title">Audit trail</h2>
                <?php if ($trail === []): ?>
                    <p class="field__hint">No decisions yet.</p>
                <?php else: ?>
                    <ul class="checklist">
                        <?php foreach ($trail as $entry): ?>
                            <li>
                                <span class="checklist__label"><?= e($entry['line']) ?></span>
                                <?php if ($entry['reason'] !== ''): ?>
                                    <span class="field__hint"><?= e($entry['reason']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

        </div>

        <div class="stack">
            <?php if ($listing['decided']): ?>
                <p class="notice notice--info">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
                    This listing has been decided. An edit by the lender sends it back to this queue.
                </p>
            <?php else: ?>
                <form class="panel stack" method="post" action="<?= e($basePath) ?>/<?= e((string) $listing['id']) ?>">
                    <?= csrf_field() ?>
                    <h2 class="panel__title">Your decision</h2>

                    <fieldset class="field">
                        <legend class="field__label">Decision</legend>
                        <?php foreach (['approve' => 'Approve as declared', 'adjust' => 'Adjust the declared value', 'reject' => 'Reject the listing'] as $value => $label): ?>
                            <label class="choice" for="decision-<?= e($value) ?>">
                                <input
                                    type="radio"
                                    id="decision-<?= e($value) ?>"
                                    name="decision"
                                    value="<?= e($value) ?>"
                                    required
                                    <?= $old['decision'] === $value ? 'checked' : '' ?>
                                >
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                        <?php if (isset($errors['decision'])): ?>
                            <span class="field__error"><?= e($errors['decision']) ?></span>
                        <?php endif; ?>
                    </fieldset>

                    <div class="field">
                        <label class="field__label" for="adjusted-value">Corrected declared value (pts) — for “Adjust”</label>
                        <input
                            class="input input--narrow"
                            type="number"
                            id="adjusted-value"
                            name="declared_value"
                            min="1"
                            step="1"
                            value="<?= e($old['declared_value']) ?>"
                            placeholder="<?= e((string) $listing['declared_value']) ?>"
                            <?= isset($errors['declared_value']) ? 'aria-invalid="true"' : '' ?>
                        >
                        <?php if (isset($errors['declared_value'])): ?>
                            <span class="field__error"><?= e($errors['declared_value']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="decision-reason">Reason — required to adjust or reject; the lender sees it</label>
                        <textarea
                            class="textarea"
                            id="decision-reason"
                            name="reason"
                            maxlength="255"
                            rows="3"
                            <?= isset($errors['reason']) ? 'aria-invalid="true"' : '' ?>
                        ><?= e($old['reason']) ?></textarea>
                        <?php if (isset($errors['reason'])): ?>
                            <span class="field__error"><?= e($errors['reason']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="actions">
                        <button class="btn btn--primary" type="submit">Record decision</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
