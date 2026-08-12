<?php

declare(strict_types=1);

/**
 * Disaster Mode activated — auto-opened once for a sponsor whose division has
 * a new active disaster. Figma: "Disaster Alert — Modal" (387:189).
 *
 * The page underneath already carries the same information in its
 * notifications row, so a no-JS visitor loses nothing but the pop-up.
 *
 * @var array $activeAlert id, division, reason, affected, liaison
 */

$activeAlert ??= [];

?>
<dialog class="modal modal--sm" id="modal-sponsor-disaster-alert">
    <div class="modal__head">
        <h2 class="modal__title">Disaster Mode activated</h2>
        <button class="modal__close" type="button" aria-label="Close" data-modal-close>✕</button>
    </div>

    <span class="badge badge--error">
        <span aria-hidden="true">!</span>
        <?= e($activeAlert['reason'] ?? 'Disaster Mode active') ?>
    </span>

    <p class="field__hint">
        A regional flood event has been declared in <?= e($activeAlert['division'] ?? 'your division') ?>.
        <?= e($activeAlert['affected'] ?? '') ?> and Disaster Mode is now active — aid vouching is
        fast-tracked and sponsors are being connected with the moderator on the ground.
    </p>

    <p class="field__hint">
        Your past contributions have already funded relief in this division. If you'd like to help
        now, you can connect directly.
    </p>

    <div class="modal__footer">
        <button class="btn btn--ghost" type="button" data-modal-close>Not now</button>
        <a class="btn btn--primary" href="/sponsor/disasters/<?= e((string) ($activeAlert['id'] ?? '')) ?>">Connect with moderator</a>
    </div>
</dialog>
