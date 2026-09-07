<?php

namespace SPPMod\SPPXDB;

use Exception;

trait XDB_Validator
{
    /**
     * Loads the schema.json for the current table if it exists.
     */
    public function getTableSchemaConfig()
    {
        $schemaPath = $this->dataDir . '/' . $this->tableName . '.schema.json';
        if (file_exists($schemaPath)) {
            $json = file_get_contents($schemaPath);
            return json_decode($json, true);
        }
        return null;
    }

    /**
     * Validates an associative array of data against the table schema.
     * Throws an Exception if validation fails.
     */
    public function validateJsonSchema(array $data)
    {
        $schema = $this->getTableSchemaConfig();
        if (!$schema) {
            return true; // No schema defined, allow loose inserts
        }

        foreach ($schema['fields'] as $field => $rules) {
            $value = $data[$field] ?? null;

            // Check required
            if (!empty($rules['required']) && $value === null) {
                throw new Exception("Validation Error: Field '{$field}' is required.");
            }

            if ($value !== null) {
                // Check type
                if (!empty($rules['type'])) {
                    $type = strtolower($rules['type']);
                    if ($type === 'int' || $type === 'integer') {
                        if (!filter_var($value, FILTER_VALIDATE_INT) && $value !== '0' && $value !== 0) {
                            throw new Exception("Validation Error: Field '{$field}' must be an integer.");
                        }
                    } elseif ($type === 'float' || $type === 'double') {
                        if (!filter_var($value, FILTER_VALIDATE_FLOAT) && $value !== '0' && $value !== 0) {
                            throw new Exception("Validation Error: Field '{$field}' must be a float.");
                        }
                    } elseif ($type === 'email') {
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception("Validation Error: Field '{$field}' must be a valid email.");
                        }
                    }
                }

                // Check min/max lengths
                if (!empty($rules['min_length']) && strlen((string)$value) < $rules['min_length']) {
                    throw new Exception("Validation Error: Field '{$field}' must be at least {$rules['min_length']} characters.");
                }
                if (!empty($rules['max_length']) && strlen((string)$value) > $rules['max_length']) {
                    throw new Exception("Validation Error: Field '{$field}' cannot exceed {$rules['max_length']} characters.");
                }
            }
        }

        return true;
    }
}
