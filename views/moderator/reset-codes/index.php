<?php

declare(strict_types=1);

/**
 * Issue a one-time password-reset code (Plan §18.1 in-person trust). The
 * moderator serves members of their own division; the Admin serves any
 * account. Check the person's NIC before issuing.
 *
 * @var string     $chrome   'moderator' or 'admin'
 * @var string     $basePath where the form posts
 * @var array      $errors   per-field messages
 * @var string     $lookup   NIC or mobile, as typed
 * @var array|null $issued   code, name, expires_hours — shown once
 * @var array|null $flash
 */

$chrome = $chrome ?? 'moderator';
$errors = $errors ?? [];
$issued = $issued ?? null;

$pageTitle = 'Password reset codes';
$navActive = '';

include __DIR__ . '/../../../partials/header.php';

?>

<header class="page-header">
    <h1 class="page-header__title">Password reset codes</h1>
</header>

<?php include __DIR__ . '/../../../partials/flash.php'; ?>

<?php if ($issued !== null): ?>
    <section class="panel panel--wide" aria-live="polite">
        <h2 class="panel__heading">Code for <?= e($issued['name']) ?></h2>
        <p class="score-hero__value"><?= e($issued['code']) ?></p>
        <p class="record-meta">
            Read it to them or write it down. It works once, for <?= e((string) $issued['expires_hours']) ?> hours,
            at <strong>Forgot password → enter a code</strong>. It is not shown again, and issuing a new code
            cancels this one.
        </p>
    </section>
<?php endif; ?>

<form class="panel panel--wide" method="post" action="<?= e($basePath) ?>">
    <?= csrf_field() ?>

    <h2 class="panel__heading">Issue a new code</h2>

    <p class="notice notice--warning">
        <svg class="icon icon--sm" aria-hidden="true"><use href="#icon-alert-triangle"></use></svg>
        Only issue a code to the account holder in person, after checking their original NIC.
    </p>

    <div class="field">
        <label class="field__label" for="reset-lookup">Their NIC number or mobile number</label>
        <input
            class="input"
            type="text"
            id="reset-lookup"
            name="lookup"
            value="<?= e($lookup ?? '') ?>"
            maxlength="20"
            autocomplete="off"
            required
            <?= isset($errors['lookup']) ? 'aria-invalid="true"' : '' ?>
        >
        <?php if (isset($errors['lookup'])): ?>
            <span class="field__error"><?= e($errors['lookup']) ?></span>
        <?php endif; ?>
    </div>

    <button class="btn btn--primary" type="submit">Issue reset code</button>
</form>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>
