<?php

declare(strict_types=1);

/**
 * Request a temporary community. Figma: "Community — Temporary Request" (77:113).
 *
 * @var string $homeCommunity  the member's unchangeable home division
 * @var array  $divisions      selectable GN divisions
 * @var array  $draft          values entered so far
 * @var array  $errors         per-field messages from the Validator
 */

// Sample view data — replaced by the controller once CommunityController lands.
$homeCommunity ??= 'Kollupitiya (home — unchanged)';

$divisions ??= ['Dehiwala', 'Bambalapitiya', 'Wellawatte', 'Mount Lavinia', 'Nugegoda'];

$draft ??= ['temporary_community' => 'Dehiwala'];

$errors ??= [];

$pageTitle = 'Request a temporary community';
$navActive = '';

include __DIR__ . '/../../partials/header.php';

?>

<h1 class="detail__title">Request a temporary community</h1>

<p class="record-meta">
    Staying somewhere else for a while? Join that GN division temporarily so you can lend
    and borrow locally.
</p>

<form class="panel panel--wide" method="post" action="/community/temporary" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="field">
        <label class="field__label" for="home-community">Home community</label>
        <input class="input" type="text" id="home-community" value="<?= e($homeCommunity) ?>" readonly>
    </div>

    <div class="field">
        <label class="field__label" for="temporary-community">Temporary community</label>
        <select class="input" id="temporary-community" name="temporary_community" required>
            <?php foreach ($divisions as $division): ?>
                <option value="<?= e($division) ?>"<?= $draft['temporary_community'] === $division ? ' selected' : '' ?>>
                    <?= e($division) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['temporary_community'])): ?>
            <span class="field__error"><?= e($errors['temporary_community']) ?></span>
        <?php endif; ?>
    </div>

    <p class="form-card__legend">Proof of temporary stay</p>

    <label class="upload-drop">
        <span class="upload-drop__glyph" aria-hidden="true">＋</span>
        <span>Upload rental agreement, employer letter, or similar</span>
        <input class="visually-hidden" type="file" name="proof" accept="image/*,application/pdf" required>
    </label>

    <p class="notice notice--info">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        Temporary membership lasts 6 months — until 17 Jan 2027 — and needs verification by
        the Dehiwala moderator. You keep full membership of your home community.
    </p>

    <div class="actions">
        <a class="btn btn--ghost" href="/settings">Cancel</a>
        <button class="btn btn--primary" type="submit">Submit for verification</button>
    </div>
</form>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
