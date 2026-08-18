<?php

declare(strict_types=1);

/**
 * A service refused input that failed a business rule (Rules/CONVENTIONS.md §8).
 *
 * Carries the same field => message shape the Validator produces, so a
 * controller re-renders the form through one path whichever layer rejected it.
 */
final class ValidationException extends RuntimeException
{
    /** @var array<string, string> */
    private array $errors;

    /**
     * @param array<string, string> $errors
     */
    public function __construct(array $errors, string $message = 'Please correct the highlighted fields.')
    {
        parent::__construct($message);

        $this->errors = $errors;
    }

    /**
     * One field, one message — the common case.
     */
    public static function field(string $field, string $message): self
    {
        return new self([$field => $message], $message);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
