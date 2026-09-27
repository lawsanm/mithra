<?php

declare(strict_types=1);

/**
 * Register, in two steps. Figma: Common → "Register — Step 1" (93:127) and
 * "Register — Step 2" (93:187); step 3 is auth/register-pending.
 *
 * Step 1 takes the personal and division details and step 2 the documents and
 * password — the fields Plan §18.1 asks for, plus the email address and
 * password that sign-in needs. Step 1's answers wait in the session, so
 * nothing is created until step 2 succeeds. The two documents are seen only by
 * the division's moderator and the Admin (Plan §25.3).
 *
 * Each step works without JavaScript and re-renders with what was typed, except
 * passwords and files, which are never sent back to the browser.
 *
 * @var int    $step         1 or 2
 * @var array  $errors       per-field messages, plus 'form' for a refusal that
 *                           belongs to the attempt rather than to one field
 * @var array  $input        step 1's values, keyed by field name
 * @var array  $divisions    id, name and district of every joinable division
 * @var string $divisionName the chosen division, on step 2
 * @var array|null $flash
 */

$step         = $step ?? 1;
$errors       = $errors ?? [];
$input        = $input ?? [];
$divisions    = $divisions ?? [];
$divisionName = $divisionName ?? '';

$old = static fn (string $field): string => (string) ($input[$field] ?? '');

$pageTitle = 'Register';
$navActive = 'register';
$pageClass = 'page--auth';

$chrome = 'public';
include __DIR__ . '/../../partials/header.php';

?>

<h1 class="auth-title"><?= $step === 1 ? 'Create your account' : 'Verify your identity' ?></h1>

<?php
$wizardSteps = [1 => 'Personal & division info', 2 => 'Document upload', 3 => 'Moderator review'];
$wizardStep  = $step;
$wizardClass = 'wizard--center';
include __DIR__ . '/../../partials/wizard-steps.php';
?>

<form class="form-card form-card--auth form-card--register" method="post" action="<?= base_url() ?>/register"<?= $step === 2 ? ' enctype="multipart/form-data"' : '' ?> novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="step" value="<?= e((string) $step) ?>">

    <?php include __DIR__ . '/../../partials/flash.php'; ?>

    <?php if (isset($errors['form'])): ?>
        <p class="notice notice--error" role="alert">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            <?= e($errors['form']) ?>
        </p>
    <?php endif; ?>

    <?php if ($step === 1): ?>

        <div class="field">
            <label class="field__label" for="register-name">Full name (as on NIC)</label>
            <input
                class="input"
                type="text"
                id="register-name"
                name="full_name"
                value="<?= e($old('full_name')) ?>"
                placeholder="M. Lawsan"
                autocomplete="name"
                <?= isset($errors['full_name']) ? 'aria-invalid="true"' : '' ?>
            >
            <?= field_error($errors, 'full_name') ?>
        </div>

        <div class="field">
            <label class="field__label" for="register-nic">NIC number</label>
            <input
                class="input"
                type="text"
                id="register-nic"
                name="nic"
                value="<?= e($old('nic')) ?>"
                placeholder="199012345V or 199012345671"
                autocapitalize="characters"
                spellcheck="false"
                <?= isset($errors['nic']) ? 'aria-invalid="true"' : '' ?>
            >
            <?= field_error($errors, 'nic') ?>
        </div>

        <div class="field">
            <label class="field__label" for="register-address">Home address</label>
            <input
                class="input"
                type="text"
                id="register-address"
                name="address"
                value="<?= e($old('address')) ?>"
                placeholder="24/3 Galle Road, Colombo 03"
                autocomplete="street-address"
                <?= isset($errors['address']) ? 'aria-invalid="true"' : '' ?>
            >
            <?= field_error($errors, 'address') ?>
        </div>

        <div class="field">
            <label class="field__label" for="register-division">GN division</label>
            <select
                class="input"
                id="register-division"
                name="gn_division_id"
                <?= isset($errors['gn_division_id']) ? 'aria-invalid="true"' : '' ?>
            >
                <option value="">Select your division</option>
                <?php foreach ($divisions as $division): ?>
                    <option
                        value="<?= e((string) $division['id']) ?>"
                        <?= $old('gn_division_id') === (string) $division['id'] ? 'selected' : '' ?>
                    ><?= e((string) $division['name']) ?> · <?= e((string) $division['district']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['gn_division_id'])): ?>
                <?= field_error($errors, 'gn_division_id') ?>
            <?php else: ?>
                <span class="field__hint">Your community — you'll lend and borrow within this division.</span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="register-phone">Mobile number</label>
            <input
                class="input"
                type="tel"
                id="register-phone"
                name="phone"
                value="<?= e($old('phone')) ?>"
                placeholder="+94 77 123 4567"
                autocomplete="tel"
                spellcheck="false"
                <?= isset($errors['phone']) ? 'aria-invalid="true"' : '' ?>
            >
            <?= field_error($errors, 'phone') ?>
        </div>

        <div class="field">
            <label class="field__label" for="register-email">Email address</label>
            <input
                class="input"
                type="email"
                id="register-email"
                name="email"
                value="<?= e($old('email')) ?>"
                placeholder="you@email.com"
                autocomplete="email"
                autocapitalize="none"
                spellcheck="false"
                <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>
            >
            <?php if (isset($errors['email'])): ?>
                <?= field_error($errors, 'email') ?>
            <?php else: ?>
                <span class="field__hint">Password-reset links are sent here. You can sign in with it or your mobile number.</span>
            <?php endif; ?>
        </div>

        <div class="actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/login">Back to login</a>
            <button class="btn btn--primary" type="submit">Continue</button>
        </div>

    <?php else: ?>

        <?php if ($errors !== []): ?>
            <p class="notice notice--warning">
                Files are not kept after a problem — choose both documents again.
            </p>
        <?php endif; ?>

        <?php
        $documents = [
            'nic_photo'     => ['NIC — front', 'Upload a clear photo of the front of your NIC'],
            'address_proof' => ['Proof of address', 'Utility bill or Grama Niladhari letter showing your division address'],
        ];
        ?>
        <?php foreach ($documents as $field => [$label, $prompt]): ?>
            <div class="field">
                <span class="field__label" id="<?= e($field) ?>-label"><?= e($label) ?></span>
                <label class="upload-drop<?= isset($errors[$field]) ? ' upload-drop--invalid' : '' ?>">
                    <span class="upload-drop__glyph" aria-hidden="true">＋</span>
                    <span data-upload-name><?= e($prompt) ?></span>
                    <input
                        class="visually-hidden"
                        type="file"
                        name="<?= e($field) ?>"
                        accept="image/jpeg,image/png,image/webp"
                        aria-labelledby="<?= e($field) ?>-label"
                        <?= isset($errors[$field]) ? 'aria-invalid="true"' : '' ?>
                    >
                </label>
                <?= field_error($errors, $field) ?>
            </div>
        <?php endforeach; ?>

        <?php $passwordPrefix = 'register'; include __DIR__ . '/../../partials/password-fields.php'; ?>

        <p class="notice notice--info">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            Your <?= e($divisionName) ?> moderator reviews these documents and may arrange a brief
            in-person verification. Only your division moderator and the Admin can see them.
        </p>

        <div class="actions">
            <a class="btn btn--ghost" href="<?= base_url() ?>/register">Back</a>
            <button class="btn btn--primary" type="submit">Submit for review</button>
        </div>

    <?php endif; ?>
</form>

<?php $pageScripts = ['upload-name.js']; ?>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
