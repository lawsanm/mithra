<?php

declare(strict_types=1);

/**
 * Branding & recognition. Figma: "Branding Upload" (387:19).
 *
 * @var array $draft  editable field values: display_name, tagline, tag_bonuses
 * @var array $wall   sponsor-wall preview: initials, name, tagline, meta
 * @var array $errors per-field messages from the Validator
 */

// Sample view data — replaced by the controller once SponsorController lands.
$draft ??= [
    'display_name' => 'Northwind Co',
    'tagline'      => 'Hardware for every home',
    'tag_bonuses'  => true,
];

$wall ??= [
    'initials' => 'N',
    'name'     => 'Northwind Co',
    'tagline'  => 'Hardware for every home',
    'meta'     => 'Supporting Mithra since 2024  ·  LKR 34,500 contributed',
];

$errors ??= [];

$pageTitle = 'Branding & recognition';
$navActive = 'branding';

include __DIR__ . '/../../../partials/header-sponsor.php';

?>

<header class="page-intro">
    <h1 class="page-intro__title">Branding &amp; recognition</h1>
</header>

<div class="panel-row">
    <form class="panel panel--half" method="post" action="/sponsor/branding" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <h2 class="panel__title">Company logo</h2>

        <label class="upload-drop">
            <span class="upload-drop__glyph" aria-hidden="true">＋</span>
            <span>Upload logo — PNG or SVG, min 400×400, transparent background preferred</span>
            <input class="visually-hidden" type="file" name="logo" accept="image/png,image/svg+xml">
        </label>

        <div class="field">
            <label class="field__label" for="display_name">Display name</label>
            <input class="input" type="text" id="display_name" name="display_name" value="<?= e($draft['display_name']) ?>" required>
            <?php if (isset($errors['display_name'])): ?>
                <span class="field__error"><?= e($errors['display_name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="tagline">Tagline (optional)</label>
            <input class="input" type="text" id="tagline" name="tagline" value="<?= e($draft['tagline']) ?>">
        </div>

        <div class="toggle-field">
            <input class="toggle" type="checkbox" id="tag_bonuses" name="tag_bonuses" value="1" <?= !empty($draft['tag_bonuses']) ? 'checked' : '' ?>>
            <label class="toggle-field__label" for="tag_bonuses">
                Tag funded bonuses &amp; aid grants — show "Supported by <?= e($draft['display_name']) ?>" on
                welcome bonuses and aid grants your contribution funded.
            </label>
        </div>

        <button class="btn btn--primary" type="submit">Save changes</button>
    </form>

    <section class="panel panel--half">
        <h2 class="panel__title">Sponsor wall preview</h2>
        <div class="media">
            <span class="avatar avatar--md"><?= e($wall['initials']) ?></span>
            <span class="media__body">
                <span class="media__title"><?= e($wall['name']) ?></span>
                <span class="media__meta"><?= e($wall['tagline']) ?></span>
                <span class="media__meta"><?= e($wall['meta']) ?></span>
            </span>
        </div>
        <p class="panel__note">
            This is how your listing appears on the public sponsor wall and in the monthly newsletter.
            All sponsors receive the same recognition.
        </p>
    </section>
</div>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
