<?php
namespace SPP\Auth;

/**
 * PermissionProviderInterface
 *
 * Application-level permission providers implement this interface to supply
 * domain-specific permission definitions and access checks.
 *
 * The framework's PermissionService delegates to registered providers.
 */
interface PermissionProviderInterface
{
    /**
     * Whether this provider handles the given permission string.
     */
    public function supports(string $permission): bool;

    /**
     * Perform the actual access check.
     *
     * @param string     $permission The permission identifier
     * @param mixed|null $context    Optional entity or context object
     * @param string|null $userId    Specific user; null = current user
     */
    public function check(string $permission, $context = null, ?string $userId = null): bool;

    /**
     * List all permissions this provider defines.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public function listPermissions(): array;
}
