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
     * Free text that must say something in words: at least one letter, in any
     * script, so Sinhala and Tamil count. "15 Hill Street" passes; "40" or
     * "12-5" does not. An empty value passes — combine with required().
     */
    public function words(string $field, string $label): self
    {
        if ($this->skip($field) || $this->value($field) === '') {
            return $this;
        }

        if (!self::hasLetters($this->value($field))) {
            $this->errors[$field] = $label . ' must be written in words, not only numbers or symbols.';
        }

        return $this;
    }

    /**
     * A person's name: letters in any script, with spaces, dots, apostrophes
     * and hyphens ("J. Kavipriya", "D'Silva", "Perera-Fernando"). No digits.
     * An empty value passes — combine with required().
     */
    public function personName(string $field, string $label): self
    {
        if ($this->skip($field) || $this->value($field) === '') {
            return $this;
        }

        if (!self::isPersonName($this->value($field))) {
            $this->errors[$field] = $label . ' can only contain letters, spaces, dots, apostrophes and hyphens.';
        }

        return $this;
    }

    /** Does the text contain at least one letter, in any script? */
    public static function hasLetters(string $value): bool
    {
        return preg_match('/\p{L}/u', $value) === 1;
    }

    /** Letters (and combining marks) with spaces, dots, apostrophes and hyphens only. */
    public static function isPersonName(string $value): bool
    {
        return self::hasLetters($value) && preg_match("/^[\\p{L}\\p{M} .'’\\-]+$/u", $value) === 1;
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

        // Casting an oversized number silently clamps it to PHP_INT_MAX/MIN.
        // Validate the range before casting, while preserving decimal leading zeros.
        $digits = ltrim(ltrim($value, '-'), '0');
        $normalized = $digits === '' ? '0' : (str_starts_with($value, '-') ? '-' : '') . $digits;
        $number = filter_var($normalized, FILTER_VALIDATE_INT);

        if ($number === false) {
            $this->errors[$field] = $label . ' is outside the supported whole-number range.';

            return $this;
        }

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
     * A real calendar date written as YYYY-MM-DD, the format a date input
     * posts. "2026-02-30" is refused. Range rules (not in the past, end after
     * start) belong to the service that knows what the dates are for. An
     * empty value passes — combine with required().
     */
    public function date(string $field, string $label): self
    {
        if ($this->skip($field) || $this->value($field) === '') {
            return $this;
        }

        if (!self::isDate($this->value($field))) {
            $this->errors[$field] = $label . ' must be a real date.';
        }

        return $this;
    }

    public static function isDate(string $value): bool
    {
        // Check the shape first: DateTime throws on embedded null bytes and
        // accepts year zero, which cannot be stored as a valid database date.
        return preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) === 1
            && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
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
