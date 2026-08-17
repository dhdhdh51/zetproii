<?php
/**
 * Lightweight server-side validator. Frontend validation is a UX nicety
 * only - every rule here MUST also be enforced server-side since the
 * frontend can never be trusted.
 */
final class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string $field, ?string $label = null): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
            $this->errors[$field][] = ($label ?? $field) . ' is required.';
        }
        return $this;
    }

    public function email(string $field): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = 'Must be a valid email address.';
        }
        return $this;
    }

    public function minLength(string $field, int $min): self
    {
        $value = $this->data[$field] ?? '';
        if (is_string($value) && strlen($value) < $min) {
            $this->errors[$field][] = "Must be at least {$min} characters.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max): self
    {
        $value = $this->data[$field] ?? '';
        if (is_string($value) && strlen($value) > $max) {
            $this->errors[$field][] = "Must not exceed {$max} characters.";
        }
        return $this;
    }

    public function numeric(string $field): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->errors[$field][] = 'Must be a number.';
        }
        return $this;
    }

    public function in(string $field, array $allowed): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field][] = 'Invalid value selected.';
        }
        return $this;
    }

    public function strongPassword(string $field): self
    {
        $value = $this->data[$field] ?? '';
        if (strlen($value) < 8) {
            $this->errors[$field][] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $value) || !preg_match('/[a-z]/', $value) || !preg_match('/[0-9]/', $value)) {
            $this->errors[$field][] = 'Password must contain uppercase, lowercase and a number.';
        }
        return $this;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /** Halts the request with 422 and the collected errors if validation failed. */
    public function validateOrFail(): void
    {
        if ($this->fails()) {
            Response::validationError($this->errors());
        }
    }
}
