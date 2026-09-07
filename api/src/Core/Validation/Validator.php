<?php

declare(strict_types=1);

namespace App\Core\Validation;

final class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $param = $parts[1] ?? null;

        match ($ruleName) {
            'required' => $this->validateRequired($field, $value),
            'string' => $value !== null && $this->validateString($field, $value),
            'integer' => $value !== null && $this->validateInteger($field, $value),
            'email' => $value !== null && $this->validateEmail($field, $value),
            'min' => $value !== null && $this->validateMin($field, $value, (int) $param),
            'max' => $value !== null && $this->validateMax($field, $value, (int) $param),
            'in' => $value !== null && $this->validateIn($field, $value, $param),
            default => null,
        };
    }

    private function validateRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->errors[$field][] = "{$field} is required.";
        }
    }

    private function validateString(string $field, mixed $value): void
    {
        if (!is_string($value)) {
            $this->errors[$field][] = "{$field} must be a string.";
        }
    }

    private function validateInteger(string $field, mixed $value): void
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            $this->errors[$field][] = "{$field} must be an integer.";
        }
    }

    private function validateEmail(string $field, mixed $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "{$field} must be a valid email.";
        }
    }

    private function validateMin(string $field, mixed $value, int $min): void
    {
        if (is_string($value) && mb_strlen($value) < $min) {
            $this->errors[$field][] = "{$field} must be at least {$min} characters.";
        }
    }

    private function validateMax(string $field, mixed $value, int $max): void
    {
        if (is_string($value) && mb_strlen($value) > $max) {
            $this->errors[$field][] = "{$field} must not exceed {$max} characters.";
        }
    }

    private function validateIn(string $field, mixed $value, string $options): void
    {
        $allowed = explode(',', $options);

        if (!in_array($value, $allowed, true)) {
            $this->errors[$field][] = "{$field} must be one of: {$options}.";
        }
    }
}
