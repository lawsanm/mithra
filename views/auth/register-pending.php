<?php

declare(strict_types=1);

/**
 * Sign-up, step 3: the application is waiting for the division moderator.
 * Figma: Common → "Register — Pending Review" (93:240).
 *
 * A pending account cannot sign in (Plan §18.1), so this page is the end of
 * sign-up rather than the start of a session.
 *
 * @var string      $firstName     the applicant's first name
 * @var string      $divisionName  the division they applied to
 * @var string|null $moderatorName its moderator, or null while it has none
 */

$firstName     = $firstName ?? '';
$divisionName  = $divisionName ?? '';
$moderatorName = $moderatorName ?? null;

$pageTitle = 'Application received';
$navActive = '';
$pageClass = 'page--auth';

$chrome = 'public';
include __DIR__ . '/../../partials/header.php';

?>

<?php
$wizardSteps = [1 => 'Personal & division info', 2 => 'Document upload', 3 => 'Moderator review'];
$wizardStep  = 3;
$wizardClass = 'wizard--center';
include __DIR__ . '/../../partials/wizard-steps.php';
?>

<section class="form-card form-card--auth form-card--register pending-card">
    <span class="pending-card__icon" aria-hidden="true">
        <svg class="icon"><use href="#icon-clock"></use></svg>
    </span>

    <h1 class="form-card__title">Pending moderator review</h1>

    <p class="pending-card__lede">
        <?php if ($moderatorName !== null): ?>
            Thanks, <?= e($firstName) ?>! <?= e((string) $moderatorName) ?>, the <?= e($divisionName) ?>
            moderator, has your application. Reviews finish within five days, and you can sign in
            as soon as yours is approved.
        <?php else: ?>
            Thanks, <?= e($firstName) ?>! <?= e($divisionName) ?> has no moderator yet, so your
            application waits until one is appointed. You can sign in as soon as it is approved.
        <?php endif; ?>
    </p>

    <p class="pending-card__note">
        <strong>While you wait, your account is empty:</strong>
        no wallet activity · no items · no bookings — everything unlocks once you're verified,
        starting with a <?= e(number_format(VerificationService::WELCOME_BONUS)) ?>-point welcome bonus.
    </p>

    <a class="btn btn--ghost" href="<?= base_url() ?>/how-it-works">Read how Mithra works</a>
</section>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
