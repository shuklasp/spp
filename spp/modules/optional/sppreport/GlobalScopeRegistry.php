<?php
/**
 * Global Scope Registry for SPPReport
 * Enforces un-bypassable Row-Level Security (RLS) constraints.
 */
namespace SPPMod\SPPReport;

class GlobalScopeRegistry
{
    private static array $scopes = [];

    /**
     * Add a global scope (e.g., 'tenant_id' => 5)
     */
    public static function addScope(string $field, $value): void
    {
        self::$scopes[$field] = $value;
    }

    public static function getScopes(): array
    {
        return self::$scopes;
    }

    public static function clearScopes(): void
    {
        self::$scopes = [];
    }
}
