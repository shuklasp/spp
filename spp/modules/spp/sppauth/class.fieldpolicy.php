<?php

namespace SPPMod\SPPAuth;

use Symfony\Component\Yaml\Yaml;

/**
 * Class FieldPolicy
 *
 * Universal Field and Entity Access Governance for the SPP Framework (Drupal Field Permissions parity).
 * Provides fine-grained view and edit access control down to individual entity properties and data fields.
 *
 * Integrates:
 *   1. Programmatic rule callbacks: FieldPolicy::registerRule()
 *   2. Declarative YAML permissions: etc/apps/{app}/field_permissions.yml
 *   3. ABAC condition evaluation: PolicyRegistry::evaluate()
 *   4. RBAC role/permission checks: SPPUser::hasPermission()
 *
 * @package SPPMod\SPPAuth
 * @author Satya Prakash Shukla
 */
class FieldPolicy
{
    /** @var array<string,array<string,array<string,callable>>> Programmatic rules [entityType][field][op] => callable */
    private static array $rules = [];

    /** @var array<string,array> Cached YAML policies by entityType */
    private static array $yamlPolicies = [];

    /** @var bool Whether app YAML policies have been loaded */
    private static bool $loaded = false;

    /**
     * Register a programmatic access rule for a specific entity field.
     *
     * @param string $entityType Entity type (e.g. 'issue', 'article', 'user')
     * @param string $field Field name (e.g. 'salary', 'estimate_hours', 'internal_notes')
     * @param string $operation 'view', 'edit', or '*' for both
     * @param callable $checker Callable returning bool: fn($user, $entity) => bool
     */
    public static function registerRule(string $entityType, string $field, string $operation, callable $checker): void
    {
        $entityType = strtolower(trim($entityType));
        $field = strtolower(trim($field));
        $op = strtolower(trim($operation));

        if (!isset(self::$rules[$entityType])) {
            self::$rules[$entityType] = [];
        }
        if (!isset(self::$rules[$entityType][$field])) {
            self::$rules[$entityType][$field] = [];
        }

        if ($op === '*') {
            self::$rules[$entityType][$field]['view'] = $checker;
            self::$rules[$entityType][$field]['edit'] = $checker;
        } else {
            self::$rules[$entityType][$field][$op] = $checker;
        }
    }

    /**
     * Check if a user has permission to view a specific field.
     *
     * @param string $entityType Entity type
     * @param string $field Field name
     * @param mixed $user User instance or null to resolve current user
     * @param mixed $entity The entity instance or context array
     * @return bool
     */
    public static function canView(string $entityType, string $field, $user = null, $entity = null): bool
    {
        return self::evaluate($entityType, $field, 'view', $user, $entity);
    }

    /**
     * Check if a user has permission to edit/update a specific field.
     *
     * @param string $entityType Entity type
     * @param string $field Field name
     * @param mixed $user User instance or null to resolve current user
     * @param mixed $entity The entity instance or context array
     * @return bool
     */
    public static function canEdit(string $entityType, string $field, $user = null, $entity = null): bool
    {
        return self::evaluate($entityType, $field, 'edit', $user, $entity);
    }

    /**
     * Filter an entity data array, removing any fields the user does not have permission for.
     *
     * @param string $entityType Entity type
     * @param array $data Entity data attributes (key => value)
     * @param mixed $user User instance or null to resolve current user
     * @param string $operation 'view' (default) or 'edit'
     * @return array Cleaned data array containing only authorized fields
     */
    public static function filterEntity(string $entityType, array $data, $user = null, string $operation = 'view'): array
    {
        $filtered = [];
        foreach ($data as $key => $val) {
            $allowed = ($operation === 'edit')
                ? self::canEdit($entityType, $key, $user, $data)
                : self::canView($entityType, $key, $user, $data);

            if ($allowed) {
                $filtered[$key] = $val;
            }
        }
        return $filtered;
    }

    /**
     * Core access evaluator combining programmatic rules, YAML declarations, ABAC, and RBAC.
     */
    public static function evaluate(string $entityType, string $field, string $operation, $user = null, $entity = null): bool
    {
        self::loadPolicies();

        $entityType = strtolower(trim($entityType));
        $field = strtolower(trim($field));
        $op = strtolower(trim($operation));

        // 1. Resolve active user
        $userObj = self::resolveUser($user);

        // 2. Superadmin / Administrator bypass
        if (self::isAdmin($userObj)) {
            return true;
        }

        // 3. Programmatic registered rules
        if (isset(self::$rules[$entityType][$field][$op])) {
            $checker = self::$rules[$entityType][$field][$op];
            try {
                return (bool)$checker($userObj, $entity);
            } catch (\Throwable $e) {
                error_log("[FieldPolicy Rule Error] ({$entityType}.{$field}): " . $e->getMessage());
                return false;
            }
        }

        // 4. YAML Declarative Policy Check
        if (isset(self::$yamlPolicies[$entityType][$field])) {
            $fieldConfig = self::$yamlPolicies[$entityType][$field];
            $policyOp = $fieldConfig[$op] ?? $fieldConfig['access'] ?? null;

            if ($policyOp !== null) {
                // Check roles restriction
                if (is_array($policyOp)) {
                    $allowedRoles = $policyOp['roles'] ?? [];
                    if (!empty($allowedRoles)) {
                        $userRoles = self::getUserRoles($userObj);
                        if (empty(array_intersect($allowedRoles, $userRoles))) {
                            return false;
                        }
                    }

                    // Check custom permission string
                    if (!empty($policyOp['permission'])) {
                        if (!is_object($userObj) || !method_exists($userObj, 'hasPermission') || !$userObj->hasPermission($policyOp['permission'])) {
                            return false;
                        }
                    }

                    return true;
                } elseif (is_string($policyOp)) {
                    // String permission check
                    if (!is_object($userObj) || !method_exists($userObj, 'hasPermission') || !$userObj->hasPermission($policyOp)) {
                        return false;
                    }
                    return true;
                } elseif (is_bool($policyOp)) {
                    return $policyOp;
                }
            }
        }

        // 5. ABAC PolicyRegistry Evaluation
        if (class_exists(PolicyRegistry::class)) {
            $permission = "{$op} {$entityType} {$field}";
            if ($userObj instanceof SPPUser) {
                try {
                    $abacResult = PolicyRegistry::evaluate($userObj, $permission, $entity);
                    if (!$abacResult) {
                        return false;
                    }
                } catch (\Throwable $e) {}
            }
        }

        // 6. RBAC standard permission naming convention
        if (is_object($userObj) && method_exists($userObj, 'hasPermission')) {
            $explicitPerm = "{$op} {$field}";
            if ($userObj->hasPermission($explicitPerm)) {
                return true;
            }
        }

        // Default open access if no restrictions exist
        return true;
    }

    /**
     * Load field permissions YAML from application config.
     */
    public static function loadPolicies(): void
    {
        if (self::$loaded) {
            return;
        }

        $context = class_exists('\SPP\Scheduler', false) ? \SPP\Scheduler::getContext() : '';
        $paths = [];

        if (defined('APP_ETC_DIR') && $context !== '') {
            $paths[] = APP_ETC_DIR . DIRECTORY_SEPARATOR . $context . DIRECTORY_SEPARATOR . 'field_permissions.yml';
            $paths[] = APP_ETC_DIR . DIRECTORY_SEPARATOR . $context . DIRECTORY_SEPARATOR . 'permissions.yml';
        }
        if (defined('SPP_APP_DIR') && $context !== '') {
            $paths[] = SPP_APP_DIR . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . $context . DIRECTORY_SEPARATOR . 'field_permissions.yml';
        }

        foreach ($paths as $file) {
            if (file_exists($file)) {
                try {
                    $parsed = Yaml::parseFile($file) ?: [];
                    if (!empty($parsed['fields']) && is_array($parsed['fields'])) {
                        foreach ($parsed['fields'] as $entType => $fields) {
                            self::$yamlPolicies[strtolower($entType)] = array_change_key_case($fields, CASE_LOWER);
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("[FieldPolicy] Failed to load {$file}: " . $e->getMessage());
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Resolve current user object.
     */
    private static function resolveUser($user)
    {
        if ($user !== null) {
            return $user;
        }

        if (class_exists(SPPAuth::class)) {
            return SPPAuth::getCurrentUser() ?: SPPAuth::getUser();
        }

        return null;
    }

    /**
     * Check if user has administrative bypass privileges.
     */
    private static function isAdmin($user): bool
    {
        if (!$user) {
            return false;
        }

        if (is_array($user)) {
            $role = $user['role'] ?? $user['roles'] ?? '';
            if (is_string($role) && in_array(strtolower($role), ['admin', 'administrator', 'superadmin'], true)) {
                return true;
            }
            if (is_array($role) && !empty(array_intersect(['admin', 'administrator', 'superadmin'], array_map('strtolower', $role)))) {
                return true;
            }
        }

        if (is_object($user)) {
            if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return true;
            }
            if (method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('administrator') || $user->hasRole('superadmin'))) {
                return true;
            }
            if (!empty($user->role) && in_array(strtolower($user->role), ['admin', 'administrator', 'superadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract array of role slugs from user.
     */
    private static function getUserRoles($user): array
    {
        if (!$user) {
            return ['anonymous'];
        }

        if (is_array($user)) {
            $roles = $user['roles'] ?? $user['role'] ?? ['authenticated'];
            return is_array($roles) ? $roles : [$roles];
        }

        if (is_object($user)) {
            if (method_exists($user, 'getRoles')) {
                return (array)$user->getRoles();
            }
            if (!empty($user->role)) {
                return is_array($user->role) ? $user->role : [$user->role];
            }
        }

        return ['authenticated'];
    }

    /**
     * Reset cached rules and policies.
     */
    public static function reset(): void
    {
        self::$rules = [];
        self::$yamlPolicies = [];
        self::$loaded = false;
    }
}
