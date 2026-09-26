<?php

declare(strict_types=1);

/**
 * Onboard a sponsor. Figma "Sponsor — Onboarding" (377:155).
 *
 * The Sponsor login section is optional: switched on, the Liaison creates the
 * company's login in the same step, already active.
 *
 * @var array $draft             values entered so far
 * @var array $errors            per-field messages from the Validator
 * @var array $agreementStatuses value => label
 * @var array $accounts          sponsor login accounts that may be linked
 */

$draft             = $draft ?? [];
$errors            = $errors ?? [];
$agreementStatuses = $agreementStatuses ?? [];

$pageTitle = 'Onboard a sponsor';
$navActive = 'sponsors';

$chrome = 'sponsor-liaison';
include __DIR__ . '/../../../partials/header.php';

?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a class="breadcrumb__link" href="<?= base_url() ?>/sponsor-liaison/sponsors">Sponsors</a>
    <span class="breadcrumb__separator" aria-hidden="true">›</span>
    <span class="breadcrumb__current" aria-current="page">New sponsor</span>
</nav>

<h1 class="detail__title">Onboard a sponsor</h1>

<?php if ($errors !== []): ?>
    <p class="notice notice--error" role="alert">Please correct the highlighted fields.</p>
<?php endif; ?>

<form class="form-card" method="post" action="<?= base_url() ?>/sponsor-liaison/sponsors" novalidate>
    <?= csrf_field() ?>

    <?php include __DIR__ . '/../../../partials/sponsor-profile-fields.php'; ?>

    <fieldset class="form-card__section">
        <legend class="form-card__legend">Sponsor login</legend>

        <div class="toggle-field">
            <input
                class="toggle"
                type="checkbox"
                id="create-login"
                name="create_login"
                value="1"
                <?= $draft['create_login'] === '1' ? 'checked' : '' ?>
            >
            <label class="toggle-field__label" for="create-login">
                Create a login so the company can sign in to its sponsor dashboard
            </label>
        </div>
        <p class="field__hint">
            The login is in the contact person's name and signs in with the contact email above,
            so both are required when this is on. Leave the existing account selection empty to
            create a new login. Leave this off to link the existing sponsor account selected above.
        </p>

        <div class="field">
            <label class="field__label" for="login-nic">Contact person's NIC number</label>
            <input
                class="input"
                type="text"
                id="login-nic"
                name="login_nic"
                value="<?= e($draft['login_nic']) ?>"
                placeholder="199012345V or 199012345671"
                autocapitalize="characters"
                spellcheck="false"
                <?= isset($errors['login_nic']) ? 'aria-invalid="true"' : '' ?>
            >
            <?= field_error($errors, 'login_nic') ?>
        </div>

        <div class="field">
            <label class="field__label" for="login-phone">Contact person's mobile number</label>
            <input
                class="input"
                type="tel"
                id="login-phone"
                name="login_phone"
                value="<?= e($draft['login_phone']) ?>"
                placeholder="077 123 4567"
                <?= isset($errors['login_phone']) ? 'aria-invalid="true"' : '' ?>
            >
            <?= field_error($errors, 'login_phone') ?>
        </div>

        <div class="field">
            <label class="field__label" for="login-address">Company address</label>
            <textarea
                class="textarea"
                id="login-address"
                name="login_address"
                rows="2"
                placeholder="120 Main Street, Colombo 10"
                <?= isset($errors['login_address']) ? 'aria-invalid="true"' : '' ?>
            ><?= e($draft['login_address']) ?></textarea>
            <?= field_error($errors, 'login_address') ?>
        </div>

        <div class="field">
            <label class="field__label" for="login-password">Starting password</label>
            <input
                class="input"
                type="password"
                id="login-password"
                name="password"
                autocomplete="new-password"
                <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?>
            >
            <?php if (isset($errors['password'])): ?>
                <span class="field__error"><?= e($errors['password']) ?></span>
            <?php else: ?>
                <span class="field__hint">
                    At least <?= e((string) RegistrationService::MIN_PASSWORD) ?> characters. Give it to
                    the sponsor in person; they can change it from their account settings.
                </span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="login-password-confirmation">Confirm starting password</label>
            <input
                class="input"
                type="password"
                id="login-password-confirmation"
                name="password_confirmation"
                autocomplete="new-password"
                <?= isset($errors['password_confirmation']) ? 'aria-invalid="true"' : '' ?>
            >
            <?= field_error($errors, 'password_confirmation') ?>
        </div>
    </fieldset>

    <div class="actions">
        <a class="btn btn--ghost" href="<?= base_url() ?>/sponsor-liaison/sponsors">Cancel</a>
        <button class="btn btn--primary" type="submit">Connect sponsor</button>
    </div>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
