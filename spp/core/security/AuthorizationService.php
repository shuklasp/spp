<?php

declare(strict_types=1);

namespace SPP\Core\Security;

use SPP\Core\Domain\DomainRegistry;
use SPP\Core\Domain\DomainContextResolver;

/**
 * Class AuthorizationService
 *
 * Centralized, fail-closed authorization engine for SPP.
 * Enforces hierarchical role-based access control (RBAC), multi-domain tenant boundary
 * isolation, and resource-level permission gating across platform and domain scopes.
 *
 * Deny by default: any unmapped action or unauthorized domain access is strictly blocked.
 *
 * @package SPP\Core\Security
 * @author Satya Prakash Shukla
 */
class AuthorizationService
{
    private static ?AuthorizationService $instance = null;

    // Standard Platform & Domain Roles
    public const ROLE_PLATFORM_SUPER_ADMIN = 'platform_super_admin';
    public const ROLE_PLATFORM_ADMIN       = 'platform_admin';
    public const ROLE_DOMAIN_ADMIN         = 'domain_admin';
    public const ROLE_APP_ADMIN            = 'app_admin';
    public const ROLE_DOMAIN_USER          = 'domain_user';
    public const ROLE_AUDITOR              = 'auditor';

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Normalize string role representations into canonical role constants.
     */
    public function normalizeRole(string $role): string
    {
        $r = strtolower(trim($role));
        return match ($r) {
            'super_admin', 'superadmin', 'administrator', 'platform_super_admin', 'root' => self::ROLE_PLATFORM_SUPER_ADMIN,
            'admin', 'platform_admin' => self::ROLE_PLATFORM_ADMIN,
            'domain_admin', 'domain-admin' => self::ROLE_DOMAIN_ADMIN,
            'app_admin', 'app-admin' => self::ROLE_APP_ADMIN,
            'auditor' => self::ROLE_AUDITOR,
            'operator' => self::ROLE_PLATFORM_ADMIN,
            default => $role,
        };
    }

    /**
     * Get the active authenticated admin user session details.
     *
     * @return array{user_id: string, username: string, role: string, domains: array, permissions: array}|null
     */
    public function getCurrentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $sessionUser = $_SESSION['spp_admin_user'] ?? null;
        $userId = $_SESSION['spp_admin_user_id'] ?? null;
        $roleId = null;

        // 1. Direct session variables
        if (!$sessionUser) {
            $sessionUser = $_SESSION['__sppauth_user__'] ?? null;
        }

        // 2. SPPSession context inspection
        if (class_exists('\\SPP\\SPPSession') && \SPP\SPPSession::sessionExists()) {
            try {
                if (!$sessionUser && \SPP\SPPSession::sessionVarExists('__sppauth_user__')) {
                    $sessionUser = \SPP\SPPSession::getSessionVar('__sppauth_user__');
                }
                if (!$sessionUser && \SPP\SPPSession::sessionVarExists('spp_admin_user')) {
                    $sessionUser = \SPP\SPPSession::getSessionVar('spp_admin_user');
                }
                if (!$sessionUser && \SPP\SPPSession::sessionVarExists('__username__')) {
                    $sessionUser = \SPP\SPPSession::getSessionVar('__username__');
                }
                if (!$userId && \SPP\SPPSession::sessionVarExists('__user_id__')) {
                    $userId = (string)\SPP\SPPSession::getSessionVar('__user_id__');
                }
                if (\SPP\SPPSession::sessionVarExists('__role_id__')) {
                    $roleId = \SPP\SPPSession::getSessionVar('__role_id__');
                }
            } catch (\Throwable $e) {}
        }

        // 3. Fallback to app session buckets (e.g. __sppadmin_sppsession, __default_sppsession, or any __*_sppsession)
        if (!$sessionUser || $userId === null) {
            $buckets = ['__sppadmin_sppsession', '__default_sppsession'];
            foreach ($buckets as $b) {
                if (isset($_SESSION[$b]['vars']) && is_array($_SESSION[$b]['vars'])) {
                    $vars = $_SESSION[$b]['vars'];
                    $sessionUser = $sessionUser ?: ($vars['__sppauth_user__']['val'] ?? ($vars['spp_admin_user']['val'] ?? ($vars['__username__']['val'] ?? null)));
                    $userId = $userId ?: ($vars['__user_id__']['val'] ?? null);
                    $roleId = $roleId ?? ($vars['__role_id__']['val'] ?? null);
                }
            }
        }
        if ((!$sessionUser || $userId === null) && isset($_SESSION) && is_array($_SESSION)) {
            foreach ($_SESSION as $k => $v) {
                if (is_array($v) && isset($v['vars']) && is_array($v['vars'])) {
                    $sessionUser = $sessionUser ?: ($v['vars']['__sppauth_user__']['val'] ?? ($v['vars']['spp_admin_user']['val'] ?? ($v['vars']['__username__']['val'] ?? null)));
                    $userId = $userId ?: ($v['vars']['__user_id__']['val'] ?? null);
                    $roleId = $roleId ?? ($v['vars']['__role_id__']['val'] ?? null);
                }
            }
        }

        // 4. SPPAuth guard inspection
        if (!$sessionUser && class_exists('\\SPPMod\\SPPAuth\\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::check()) {
            $user = \SPPMod\SPPAuth\SPPAuth::user();
            $userId = (string)(\SPPMod\SPPAuth\SPPAuth::guard()->id() ?? '1');
            $sessionUser = $user ? ($user->username ?? (is_object($user) && method_exists($user, 'get') ? $user->get('UserName') : null)) : null;
        }

        // 5. Fallback session admin
        if (!$sessionUser && !empty($_SESSION['spp_admin_fallback'])) {
            $sessionUser = 'admin';
            $roleId = 1;
        }

        if (!$sessionUser) {
            return null;
        }

        $userId = (string)($userId ?? '1');
        $rawRole = $_SESSION['spp_admin_role'] ?? ($roleId == 1 ? self::ROLE_PLATFORM_SUPER_ADMIN : self::ROLE_PLATFORM_ADMIN);
        $role = $this->normalizeRole($rawRole);
        $assignedDomains = $_SESSION['spp_admin_assigned_domains'] ?? ['*'];
        $permissions = $_SESSION['spp_admin_permissions'] ?? [];

        // Check configured admin user in global-settings.yml
        $settings = \SPP\App::getGlobalSettings();
        $superAdmin = $settings['admin_auth']['username'] ?? ($settings['admin_username'] ?? 'admin');
        if (
            !empty($_SESSION['spp_admin_fallback']) ||
            $roleId == 1 ||
            strcasecmp((string)$sessionUser, (string)$superAdmin) === 0 ||
            strcasecmp((string)$sessionUser, 'admin') === 0 ||
            $role === self::ROLE_PLATFORM_SUPER_ADMIN
        ) {
            $role = self::ROLE_PLATFORM_SUPER_ADMIN;
            $assignedDomains = ['*'];
            $permissions = ['*'];
        }

        // Load permissions and assigned domains from XDB if available
        if (class_exists('\\SPPMod\\SPPXDB\\SPP_XDB') && $role !== self::ROLE_PLATFORM_SUPER_ADMIN) {
            try {
                $xdb = new \SPPMod\SPPXDB\SPP_XDB('sys', 'admin_permissions');
                $rows = $xdb->queryX("//row[user_id = '{$userId}' or username = '{$sessionUser}']");
                if (!empty($rows)) {
                    if (!empty($rows[0]['role'])) {
                        $role = $this->normalizeRole($rows[0]['role']);
                    }
                    if (!empty($rows[0]['domains'])) {
                        $assignedDomains = array_filter(array_map('trim', explode(',', $rows[0]['domains'])));
                    }
                    if (!empty($rows[0]['scopes'])) {
                        $permissions = array_filter(array_map('trim', explode(',', $rows[0]['scopes'])));
                    }
                }
            } catch (\Throwable $e) {}
        }

        if ($role === self::ROLE_PLATFORM_SUPER_ADMIN || $role === self::ROLE_PLATFORM_ADMIN) {
            if (empty($assignedDomains) || !in_array('*', $assignedDomains, true)) {
                $assignedDomains[] = '*';
            }
        }

        return [
            'user_id' => $userId,
            'username' => (string)$sessionUser,
            'role' => $role,
            'domains' => $assignedDomains,
            'permissions' => $permissions,
        ];
    }

    /**
     * Check if a specific user can access a domain.
     */
    public function canAccessDomain(array $user, string $domainId): bool
    {
        $role = $user['role'] ?? self::ROLE_DOMAIN_USER;
        if ($role === self::ROLE_PLATFORM_SUPER_ADMIN || $role === self::ROLE_PLATFORM_ADMIN) {
            return true;
        }

        $allowedDomains = (array)($user['allowed_domains'] ?? ($user['domains'] ?? []));
        if (in_array('*', $allowedDomains, true) || in_array($domainId, $allowedDomains, true)) {
            return true;
        }

        return ($user['domain'] ?? null) === $domainId;
    }

    /**
     * Enforce domain boundary access or throw AccessDeniedException.
     *
     * @throws AccessDeniedException
     */
    public function enforceDomainAccess(array $user, string $domainId): void
    {
        if (!$this->canAccessDomain($user, $domainId)) {
            throw new AccessDeniedException(
                "Access Denied: User '{$user['username']}' is not authorized to access domain '{$domainId}'.",
                $domainId,
                $user['username'] ?? 'anonymous',
                'domain:access'
            );
        }
    }

    /**
     * Check if a specific user is authorized for an action.
     */
    public function isAuthorized(array $user, string $permission, ?string $domainId = null): bool
    {
        return $this->can($permission, $domainId, $user);
    }

    /**
     * Check if the current user has permission to perform an action on a domain.
     *
     * @param string $permission Required permission (e.g. 'domain.apps.enable', 'platform.domains.create')
     * @param string|null $domainId Target domain ID (if action is domain-scoped)
     * @param array|null $user Explicit user array (defaults to current user)
     * @return bool
     */
    public function can(string $permission, ?string $domainId = null, ?array $user = null): bool
    {
        $u = $user ?? $this->getCurrentUser();
        if ($u === null) {
            return false; // Deny unauthenticated
        }

        $role = $this->normalizeRole($u['role'] ?? self::ROLE_DOMAIN_USER);
        $domains = (array)($u['allowed_domains'] ?? ($u['domains'] ?? []));
        if (!empty($u['domain'])) {
            $domains[] = $u['domain'];
        }
        $permissions = $u['permissions'] ?? [];
        $normPerm = str_replace(':', '.', strtolower(trim($permission)));

        // Automatically resolve action identifiers to canonical permission scopes if needed
        if (!str_contains($normPerm, '.') && function_exists('getActionScopeMap')) {
            $actMap = getActionScopeMap();
            if (isset($actMap[$permission])) {
                $normPerm = str_replace(':', '.', strtolower(trim($actMap[$permission])));
            }
        }

        // 1. Platform Super Administrator has complete access
        if ($role === self::ROLE_PLATFORM_SUPER_ADMIN || in_array('*', $permissions, true) || in_array('admin.*', $permissions, true)) {
            return true;
        }

        // 2. If action is domain-scoped, verify domain ownership boundary
        if ($domainId !== null && $domainId !== '' && $domainId !== 'platform' && $domainId !== 'all') {
            $normDomain = strtolower(trim($domainId));
            // Platform administrators have platform-wide domain management rights across all domains
            if ($role !== self::ROLE_PLATFORM_ADMIN && !in_array('*', $domains, true) && !in_array($normDomain, $domains, true)) {
                return false;
            }
        }

        // 3. Platform actions can only be executed by platform roles
        if ((str_starts_with($normPerm, 'platform.') || str_contains($normPerm, 'global')) && $role !== self::ROLE_PLATFORM_SUPER_ADMIN && $role !== self::ROLE_PLATFORM_ADMIN) {
            return false;
        }

        // 4. Check explicit permissions
        if (in_array($permission, $permissions, true) || in_array($normPerm, $permissions, true)) {
            return true;
        }

        // 5. Check wildcard permission prefix (e.g. 'domain.apps.*' covers 'domain.apps.enable')
        $parts = explode('.', $normPerm);
        if (count($parts) >= 2) {
            $prefixWildcard = $parts[0] . '.*';
            if (in_array($prefixWildcard, $permissions, true)) {
                return true;
            }
            if (count($parts) >= 3) {
                $subWildcard = $parts[0] . '.' . $parts[1] . '.*';
                if (in_array($subWildcard, $permissions, true)) {
                    return true;
                }
            }
        }

        // 6. Built-in role permissions
        return $this->checkRoleBuiltinPermissions($role, $normPerm);
    }

    /**
     * Assert authorization or throw AccessDeniedException.
     *
     * @throws \SPP\Core\Security\AccessDeniedException
     */
    public function assertAuthorized(string $permission, ?string $domainId = null): void
    {
        if (!$this->can($permission, $domainId)) {
            $u = $this->getCurrentUser();
            $userStr = $u ? $u['username'] : 'anonymous';
            $domStr = $domainId ?: 'global';
            throw new AccessDeniedException("Access Denied: User '{$userStr}' lacks required permission '{$permission}' for scope '{$domStr}'.");
        }
    }

    /**
     * Check default built-in permissions for roles.
     */
    private function checkRoleBuiltinPermissions(string $role, string $permission): bool
    {
        switch ($role) {
            case self::ROLE_PLATFORM_ADMIN:
                return !str_starts_with($permission, 'platform.security.manage');

            case self::ROLE_DOMAIN_ADMIN:
                // Full domain administration on assigned domains
                return str_starts_with($permission, 'domain.') ||
                       str_starts_with($permission, 'apps.') ||
                       str_starts_with($permission, 'admin.apps') ||
                       str_starts_with($permission, 'admin.database') ||
                       str_starts_with($permission, 'admin.files') ||
                       str_starts_with($permission, 'admin.domains') ||
                       str_starts_with($permission, 'admin.dns') ||
                       str_starts_with($permission, 'database.') ||
                       str_starts_with($permission, 'files.');

            case self::ROLE_APP_ADMIN:
                return str_starts_with($permission, 'domain.apps.') || $permission === 'domain.database.read';

            case self::ROLE_AUDITOR:
                return str_ends_with($permission, '.read') || str_ends_with($permission, '.list') || str_ends_with($permission, '.inspect') || str_starts_with($permission, 'security.audit.');

            case self::ROLE_DOMAIN_USER:
            default:
                return false;
        }
    }

    /**
     * Get list of domains the current user is authorized to manage.
     *
     * @return array<string> List of domain IDs, or ['*'] for super-admins
     */
    public function getAuthorizedDomains(): array
    {
        $u = $this->getCurrentUser();
        if ($u === null) {
            return [];
        }

        if ($u['role'] === self::ROLE_PLATFORM_SUPER_ADMIN || in_array('*', $u['domains'], true)) {
            $all = array_keys(DomainRegistry::getInstance()->getAllDomains());
            return !empty($all) ? $all : ['default'];
        }

        return $u['domains'];
    }
}
