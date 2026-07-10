<?php

namespace SPP\Core;

/**
 * Class SchemaValidator
 * Unified identifier sanitization engine for safe SQL DDL operations.
 */
class SchemaValidator
{
    /**
     * Safely validates and escapes a database, table, or column identifier.
     * Enforces strict regex validation to prevent SQL injection in DDL statements.
     *
     * @param string $identifier The raw identifier name.
     * @return string The backtick-enclosed, sanitized identifier.
     * @throws \InvalidArgumentException If the identifier contains illegal characters.
     */
    public static function escapeIdentifier(string $identifier): string
    {
        $clean = str_replace('`', '', trim($identifier));
        
        if ($clean === '') {
            throw new \InvalidArgumentException("Schema identifier cannot be empty.");
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $clean)) {
            throw new \InvalidArgumentException("Security Exception: Invalid identifier '{$clean}'. Contains illegal characters.");
        }

        return '`' . $clean . '`';
    }
}
