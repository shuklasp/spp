<?php

namespace SPPMod\SPPView;

/**
 * class ValidationResult
 * Encapsulates the results of a validation run.
 */
class ValidationResult
{
    private bool $valid = true;
    private array $errors = [];
    private array $validatedData = [];

    public function __construct(bool $valid = true, array $errors = [], array $validated = [])
    {
        $this->valid = $valid;
        $this->errors = $errors;
        $this->validatedData = $validated;
    }

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
        if (empty($this->errors)) {
            return null;
        }
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

    public function passes(): bool
    {
        return $this->isValid();
    }

    public function fails(): bool
    {
        return !$this->isValid();
    }

    public function errors(): array
    {
        return $this->getErrors();
    }
    /**
     * Store the filtered, validated data slice.
     */
    public function setValidatedData(array $data): self
    {
        $this->validatedData = $data;
        return $this;
    }

    /**
     * Get the validated data or a specific key.
     */
    public function validated(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->validatedData;
        }
        return $this->validatedData[$key] ?? $default;
    }

    /**
     * Return only specific validated keys.
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->validatedData, array_flip($keys));
    }

    /**
     * Return all validated keys except specific ones.
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->validatedData, array_flip($keys));
    }

    /**
     * Magic property access to validated fields (e.g. $validation->email).
     */
    public function __get(string $name): mixed
    {
        return $this->validatedData[$name] ?? null;
    }

    /**
     * Magic property existence check for validated fields.
     */
    public function __isset(string $name): bool
    {
        return isset($this->validatedData[$name]);
    }
}
