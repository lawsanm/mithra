<?php

declare(strict_types=1);

/**
 * Numbered progress through a multi-step flow: finished steps show a tick and
 * a green connector, the current step is highlighted.
 *
 * @var array<int, string> $wizardSteps step number (from 1) => label
 * @var int                $wizardStep  the current step
 * @var string             $wizardClass extra modifier, e.g. wizard--compact
 */

$wizardClass = $wizardClass ?? '';

?>
<ol class="wizard<?= $wizardClass !== '' ? ' ' . e($wizardClass) : '' ?>">
    <?php foreach ($wizardSteps as $number => $label): ?>
        <?php
        $isDone    = $number < $wizardStep;
        $isCurrent = $number === $wizardStep;
        ?>
        <?php if ($number > 1): ?>
            <li aria-hidden="true">
                <hr class="wizard__connector<?= $number <= $wizardStep ? ' wizard__connector--done' : '' ?>">
            </li>
        <?php endif; ?>
        <li class="wizard__step">
            <span class="wizard__marker<?= $isCurrent ? ' wizard__marker--current' : ($isDone ? ' wizard__marker--done' : '') ?>">
                <?= $isDone ? '✓' : e((string) $number) ?>
            </span>
            <span class="wizard__label<?= $isCurrent ? ' wizard__label--current' : '' ?>"
                <?= $isCurrent ? 'aria-current="step"' : '' ?>><?= e($label) ?></span>
        </li>
    <?php endforeach; ?>
</ol>
