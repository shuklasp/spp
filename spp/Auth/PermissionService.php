<?php
namespace SPP\Auth;

/**
 * PermissionService
 *
 * A lightweight, framework-level permission service that augments the existing
 * IAM / SPPAuth layer with fine-grained, entity-level permission checking.
 *
 * Philosophy:
 *  - The core provides the *mechanism* (check / register / grant).
 *  - Applications provide the *policy* via PermissionProviders.
 *  - Stays compatible with the existing roles/rights/entity_roles tables.
 */
class PermissionService
{
    /** @var array<string, PermissionProviderInterface> */
    private static array $providers = [];

    /** @var array<string, array<string, bool>> Runtime cache: role => [permission => bool] */
    private static array $cache = [];

    // ── Provider Registration ──────────────────────────────────────────

    /**
     * Register an application-level permission provider.
     * Each provider supplies a list of permissions it knows about and
     * can answer access-check queries for its domain.
     */
    public static function registerProvider(string $domain, PermissionProviderInterface $provider): void
    {
        self::$providers[$domain] = $provider;
        self::$cache = []; // invalidate
    }

    // ── Permission Checking ────────────────────────────────────────────

    /**
     * Check whether the current user (or a specific user) has a permission.
     *
     * @param string      $permission  e.g. 'content.create', 'node.delete.own'
     * @param mixed|null  $context     Optional context (entity, bundle, etc.)
     * @param string|null $userId      Check for a specific user; null = current user.
     */
    public static function can(string $permission, $context = null, ?string $userId = null): bool
    {
        // 1. Try SPPAuth first (the existing RBAC layer)
        if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            if (\SPPMod\SPPAuth\SPPAuth::can($permission)) {
                return true;
            }
        }

        // 2. Try YAML-based RBAC (etc/rbac.yml)
        if (self::checkYamlRbac($permission, $userId)) {
            return true;
        }

        // 3. Ask registered providers
        foreach (self::$providers as $domain => $provider) {
            if ($provider->supports($permission)) {
                return $provider->check($permission, $context, $userId);
            }
        }

        return false;
    }

    /**
     * Return all permissions known to every registered provider.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function allPermissions(): array
    {
        $all = [];
        foreach (self::$providers as $domain => $provider) {
            foreach ($provider->listPermissions() as $key => $meta) {
                $all["{$domain}.{$key}"] = $meta;
            }
        }
        return $all;
    }

    // ── Internal Helpers ───────────────────────────────────────────────

    private static function checkYamlRbac(string $permission, ?string $userId): bool
    {
        $rbacPath = (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__)) . '/etc/rbac.yml';
        if (!file_exists($rbacPath)) {
            return false;
        }

        try {
            $config = \Symfony\Component\Yaml\Yaml::parseFile($rbacPath);
        } catch (\Exception $e) {
            return false;
        }

        $roles = $config['roles'] ?? [];

        // Determine which roles the user holds
        $userRoles = self::getUserRoles($userId);

        foreach ($userRoles as $roleName) {
            $roleDef = $roles[$roleName] ?? null;
            if (!$roleDef) continue;
            $perms = $roleDef['permissions'] ?? [];
            if (in_array('*', $perms, true) || in_array($permission, $perms, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the role slugs assigned to a user.
     */
    private static function getUserRoles(?string $userId): array
    {
        if (!class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            return [];
        }

        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $targetId = $userId;

            if ($targetId === null && class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
                $currentUser = \SPPMod\SPPAuth\SPPAuth::getCurrentUser();
                $targetId = $currentUser['username'] ?? ($currentUser['id'] ?? null);
            }

            if (!$targetId) return [];

            $sql = 'SELECT r.role_name FROM '
                . \SPPMod\SPPDB\SPPDB::sppTable('entity_roles') . ' er '
                . 'JOIN ' . \SPPMod\SPPDB\SPPDB::sppTable('roles') . ' r ON er.role_id = r.id '
                . 'WHERE er.target_id = ?';

            $rows = $db->execute_query($sql, [$targetId]);
            return array_column($rows, 'role_name');
        } catch (\Exception $e) {
            return [];
        }
    }
}
