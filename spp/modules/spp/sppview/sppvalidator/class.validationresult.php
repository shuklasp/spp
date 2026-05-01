<?php

namespace SPPMod\SPPView;

/**
 * class ValidationResult
 * Encapsulates the results of a validation run.
 */
class ValidationResult {
    private bool $valid = true;
    private array $errors = [];

    public function addError(string $field, string $message): void
    {
        $this->valid = false;
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        if (empty($this->errors)) return null;
        $firstField = reset($this->errors);
        return reset($firstField);
    }

    public function getAllErrors(): array
    {
        $all = [];
        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $err) {
                $all[] = $err;
            }
        }
        return $all;
    }
}
