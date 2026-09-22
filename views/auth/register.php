<?php

declare(strict_types=1);

/**
 * Register. The sign-up half of Figma: Common → "Login" (93:282), built from
 * the same auth card.
 *
 * The form works without JavaScript and re-renders with everything the
 * applicant typed except the two passwords, which are never sent back to the
 * browser.
 *
 * The fields are the ones Proposal §19.1 asks for — name, address, NIC, GN
 * division, mobile — plus the email address and password that sign-in needs.
 *
 * @var array  $errors    per-field messages, plus 'form' for a refusal that
 *                        belongs to the attempt rather than to one field
 * @var array  $input     previously submitted values, keyed by field name
 * @var array  $divisions id, name and district of every joinable division
 * @var array|null $flash
 */

$errors    = $errors ?? [];
$input     = $input ?? [];
$divisions = $divisions ?? [];

$old = static fn (string $field): string => (string) ($input[$field] ?? '');

$pageTitle = 'Register';
$navActive = 'register';

include __DIR__ . '/../../partials/header-public.php';

?>

<form class="form-card form-card--auth" method="post" action="<?= base_url() ?>/register">
    <?= csrf_field() ?>

    <h1 class="form-card__title">Join your community</h1>

    <?php include __DIR__ . '/../../partials/flash.php'; ?>

    <?php if (isset($errors['form'])): ?>
        <p class="notice notice--error" role="alert">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
            <?= e($errors['form']) ?>
        </p>
    <?php endif; ?>

    <p class="notice notice--info">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-info"></use></svg>
        Your division moderator checks these details against the GN register before your
        account opens. That review takes up to five days.
    </p>

    <div class="field">
        <label class="field__label" for="register-name">Full name</label>
        <input
            class="input"
            type="text"
            id="register-name"
            name="full_name"
            value="<?= e($old('full_name')) ?>"
            placeholder="As written on your NIC"
            maxlength="150"
            autocomplete="name"
            required
            <?= isset($errors['full_name']) ? 'aria-invalid="true"' : '' ?>
        >
        <?php if (isset($errors['full_name'])): ?>
            <span class="field__error"><?= e($errors['full_name']) ?></span>
        <?php endif; ?>
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
            maxlength="20"
            autocapitalize="characters"
            spellcheck="false"
            required
            <?= isset($errors['nic']) ? 'aria-invalid="true"' : '' ?>
        >
        <?php if (isset($errors['nic'])): ?>
            <span class="field__error"><?= e($errors['nic']) ?></span>
        <?php else: ?>
            <span class="field__hint">Your moderator checks this against the division register.</span>
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
            placeholder="077 123 4567"
            maxlength="20"
            autocomplete="tel"
            spellcheck="false"
            required
            <?= isset($errors['phone']) ? 'aria-invalid="true"' : '' ?>
        >
        <?php if (isset($errors['phone'])): ?>
            <span class="field__error"><?= e($errors['phone']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="register-email">Email address — optional</label>
        <input
            class="input"
            type="email"
            id="register-email"
            name="email"
            value="<?= e($old('email')) ?>"
            placeholder="you@email.com"
            maxlength="150"
            autocomplete="email"
            autocapitalize="none"
            spellcheck="false"
            <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>
        >
        <?php if (isset($errors['email'])): ?>
            <span class="field__error"><?= e($errors['email']) ?></span>
        <?php else: ?>
            <span class="field__hint">Leave this empty to sign in with your mobile number.</span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="register-division">GN division</label>
        <select
            class="input"
            id="register-division"
            name="gn_division_id"
            required
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
            <span class="field__error"><?= e($errors['gn_division_id']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="register-address">Home address</label>
        <textarea
            class="input"
            id="register-address"
            name="address"
            rows="2"
            maxlength="255"
            placeholder="24/3 Galle Road, Colombo 03"
            autocomplete="street-address"
            required
            <?= isset($errors['address']) ? 'aria-invalid="true"' : '' ?>
        ><?= e($old('address')) ?></textarea>
        <?php if (isset($errors['address'])): ?>
            <span class="field__error"><?= e($errors['address']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="register-password">Password</label>
        <input
            class="input"
            type="password"
            id="register-password"
            name="password"
            minlength="<?= e((string) RegistrationService::MIN_PASSWORD) ?>"
            autocomplete="new-password"
            required
            <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?>
        >
        <?php if (isset($errors['password'])): ?>
            <span class="field__error"><?= e($errors['password']) ?></span>
        <?php else: ?>
            <span class="field__hint">
                At least <?= e((string) RegistrationService::MIN_PASSWORD) ?> characters. A short
                phrase you will remember beats a short word you will not.
            </span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="register-password-confirmation">Confirm password</label>
        <input
            class="input"
            type="password"
            id="register-password-confirmation"
            name="password_confirmation"
            autocomplete="new-password"
            required
            <?= isset($errors['password_confirmation']) ? 'aria-invalid="true"' : '' ?>
        >
        <?php if (isset($errors['password_confirmation'])): ?>
            <span class="field__error"><?= e($errors['password_confirmation']) ?></span>
        <?php endif; ?>
    </div>

    <button class="btn btn--primary btn--block" type="submit">Apply to join</button>

    <div class="auth-links">
        <a class="link" href="<?= base_url() ?>/login">Already a member? Log in</a>
    </div>
</form>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
