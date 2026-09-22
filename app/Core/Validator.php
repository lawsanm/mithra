<?php

declare(strict_types=1);

/**
 * Shared input validator (Rules/CONVENTIONS.md §8).
 *
 * Controllers validate at the boundary before calling a service: required,
 * type, length, range, enum. Values are kept as the member typed them so a
 * failed form re-renders with their own input and a message per field.
 *
 * Rules chain, and a field that already failed is skipped by later rules — one
 * message per field, never a pile-up.
 */
final class Validator
{
    /** @var array<string, string> trimmed scalar input */
    private array $data = [];

    /** @var array<string, string> field => first message */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data raw request input
     */
    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            $this->data[(string) $key] = is_scalar($value) ? trim((string) $value) : '';
        }
    }

    public function value(string $field, string $default = ''): string
    {
        $value = $this->data[$field] ?? '';

        return $value === '' ? $default : $value;
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        return $this->data;
    }

    public function required(string $field, string $label): self
    {
        if ($this->skip($field)) {
            return $this;
        }

        if ($this->value($field) === '') {
            $this->errors[$field] = $label . ' is required.';
        }

        return $this;
    }

    public function maxLength(string $field, string $label, int $max): self
    {
        if ($this->skip($field)) {
            return $this;
        }

        if (mb_strlen($this->value($field)) > $max) {
            $this->errors[$field] = sprintf('%s must be %d characters or fewer.', $label, $max);
        }

        return $this;
    }

    /**
     * Whole number within an inclusive range. An empty value passes — combine
     * with required() when the field is mandatory.
     */
    public function integer(string $field, string $label, int $min = 0, ?int $max = null): self
    {
        if ($this->skip($field)) {
            return $this;
        }

        $value = $this->value($field);

        if ($value === '') {
            return $this;
        }

        if (preg_match('/^-?\d+$/', $value) !== 1) {
            $this->errors[$field] = $label . ' must be a whole number.';

            return $this;
        }

        $number = (int) $value;

        if ($number < $min) {
            $this->errors[$field] = sprintf('%s must be at least %d.', $label, $min);

            return $this;
        }

        if ($max !== null && $number > $max) {
            $this->errors[$field] = sprintf('%s must be %d or less.', $label, $max);
        }

        return $this;
    }

    /**
     * @param list<string> $allowed
     */
    public function inList(string $field, string $label, array $allowed): self
    {
        if ($this->skip($field)) {
            return $this;
        }

        if (!in_array($this->value($field), $allowed, true)) {
            $this->errors[$field] = 'Choose a valid ' . mb_strtolower($label) . '.';
        }

        return $this;
    }

    public function addError(string $field, string $message): self
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }

        return $this;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Stop when the whole set already failed on this field.
     */
    private function skip(string $field): bool
    {
        return isset($this->errors[$field]);
    }
}
