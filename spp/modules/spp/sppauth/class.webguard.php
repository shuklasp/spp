<?php
namespace SPPMod\SPPAuth;

/**
 * class WebGuard
 * 
 * Standard session-based authentication driver.
 */
class WebGuard implements GuardInterface {
    private ?object $user = null;
    private string $sessionKey = '__sppauth_user__';

    private array $permissionCache = [];

    public function check(): bool {
        $user = $this->user();
        return $user && !($user instanceof AnonymousUser);
    }

    /**
     * Determine if the user has a specific permission.
     */
    public function can(string $permission): bool {
        if (empty($this->permissionCache)) {
            $this->resolvePermissions();
        }
        

        return in_array($permission, $this->permissionCache) || in_array('*', $this->permissionCache);
    }

    /**
     * Resolve all permissions from groups and roles.
     */
    private function resolvePermissions(): void {
        $user = $this->user();
        if (!$user) return;

        // 1. Mandatory 'Anonymous' group permissions for everyone
        if (class_exists('\SPPMod\SPPGroup\SPPGroup')) {
            $anonGroup = new \SPPMod\SPPGroup\SPPGroup();
            try {
                $anonGroup->load('anonymous');
                if ($anonGroup->id) {
                    $this->collectGroupPermissions($anonGroup);
                }
            } catch (\Exception $e) {}

            // 2. Mandatory 'Authenticated' group permissions for logged in users
            if ($this->check()) {
                $authGroup = new \SPPMod\SPPGroup\SPPGroup();
                try {
                    $authGroup->load('authenticated');
                    if ($authGroup->id) {
                        $this->collectGroupPermissions($authGroup);
                    }
                } catch (\Exception $e) {}
            }
        }

        // 3. Get legacy rights from SPPUser if it exists
        if ($user instanceof SPPUser) {
            $this->permissionCache = array_merge($this->permissionCache, $user->get('rights') ?: []);
        }

        // 4. Get direct permissions from Registry (Override)
        $registryRights = \SPP\Registry::get("user=>{$user->id}=>rights");
        if ($registryRights) {
            $this->permissionCache = array_merge($this->permissionCache, (array)$registryRights);
        }

        // 5. Get permissions from assigned Groups (Polymorphic RBAC)
        if (class_exists('\SPPMod\SPPGroup\SPPGroup')) {
            $groups = \SPPMod\SPPGroup\SPPGroupLoader::getGroupsForMember(get_class($user), $user->id);
            foreach ($groups as $group) {
                $this->collectGroupPermissions($group);
            }
        }

        $this->permissionCache = array_unique($this->permissionCache);
    }

    private function collectGroupPermissions($group): void {
        // Collect roles from group metadata
        $roles = (array) $group->get('roles');
        foreach ($roles as $roleSlug) {
            $this->permissionCache = array_merge($this->permissionCache, $this->resolveRolePermissions($roleSlug));
        }
        
        // Collect direct rights from group
        $rights = (array) $group->get('rights');
        $this->permissionCache = array_merge($this->permissionCache, $rights);
    }

    private function resolveRolePermissions(string $roleSlug): array {
        // In a real implementation, this would hit the DB via RBAC Role entity
        // For now, we use a registry override for performance and flexibility
        return (array) \SPP\Registry::get("rbac=>roles=>{$roleSlug}=>permissions", []);
    }

    public function user() {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $userId = \SPP\SPPSession::sessionVarExists($this->sessionKey) ? \SPP\SPPSession::getSessionVar($this->sessionKey) : null;
        if ($userId) {
            try {
                $this->user = new SPPUser($userId);
            } catch (\Exception $e) {
                $this->user = new class($userId) {
                    public $id;
                    public function __construct($id) { $this->id = $id; }
                    public function getId() { return $this->id; }
                };
            }
        } else {
            $this->user = new AnonymousUser();
        }

        return $this->user;
    }

    public function id() {
        $user = $this->user();
        return $user ? $user->id : null;
    }

    public function login($user, bool $remember = false) {
        $id = is_object($user) ? $user->id : $user;
        \SPP\SPPSession::setSessionVar($this->sessionKey, $id);
        $this->user = is_object($user) ? $user : null;
        
        $params = ['user' => $user];
        \SPP\SPPEvent::fireEvent('event_spp_auth_login', $params);
    }

    public function logout() {
        \SPP\SPPSession::unsetSessionVar($this->sessionKey);
        $this->user = null;
        session_destroy();
        
        $params = [];
        \SPP\SPPEvent::fireEvent('event_spp_auth_logout', $params);
    }
}
