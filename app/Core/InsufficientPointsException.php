<?php

declare(strict_types=1);

/**
 * A point movement would take a pool or wallet below zero (Plan §7.7,
 * Rules/CONVENTIONS.md §8).
 *
 * Thrown inside the movement's transaction, before anything is written, so the
 * caller rolls back and no balance ever goes negative.
 */
final class InsufficientPointsException extends RuntimeException
{
}
