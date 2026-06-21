<?php

namespace SPPMod\SPPAuth;

/**
 * class WebGuard
 *
 * Standard session-based authentication driver.
 */
class WebGuard implements GuardInterface
{
    private ?object $user = null;
    private string $sessionKey = '__sppauth_user__';
    private string $impersonateSessionKey = '__sppauth_impersonator__';
    private string $sudoSessionKey = '__sppauth_sudo_expires__';
    private string $mfaSessionKey = '__sppauth_2fa_pending__';

    private array $permissionCache = [];

    public function check(): bool
    {
        $user = $this->user();
        if (!$user || $user instanceof AnonymousUser) {
            return false;
        }
        
        if (\SPP\SPPSession::sessionVarExists($this->mfaSessionKey)) {
            $mfaTime = \SPP\SPPSession::sessionVarExists('__sppauth_2fa_time__') ? \SPP\SPPSession::getSessionVar('__sppauth_2fa_time__') : 0;
            if (time() - $mfaTime > 300) { // 5 minutes timeout
                $this->logout();
            }
            return false;
        }

        // 1. Verify Fingerprint (Anti-Hijacking)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $currentFingerprint = hash('sha256', $ip . $ua);
        $sessionFingerprint = \SPP\SPPSession::sessionVarExists('__sppauth_fingerprint__') ? \SPP\SPPSession::getSessionVar('__sppauth_fingerprint__') : null;
        
        if ($sessionFingerprint !== $currentFingerprint) {
            $this->logout();
            return false;
        }

        // 2. Session Revocation Check (Device Management)
        $sessid = session_id();
        if ($sessid) {
            $lastHeartbeat = \SPP\SPPSession::sessionVarExists('__sppauth_last_heartbeat__') 
                ? \SPP\SPPSession::getSessionVar('__sppauth_last_heartbeat__') : 0;
            
            // Only query the database every 60 seconds to avoid Ping of Death
            if (time() - $lastHeartbeat >= 60) {
                $db = new \SPPMod\SPPDB\SPPDB();
                $result = $db->execute_query('SELECT 1 FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('loginrec') . ' WHERE sessid=?', [$sessid]);
                
                if (empty($result)) {
                    // Session was revoked from the database
                    $this->logout();
                    return false;
                }
                
                \SPP\SPPSession::setSessionVar('__sppauth_last_heartbeat__', time());
                // Update lastaccess for accurate active devices dashboard
                $db->execute_query('UPDATE ' . \SPPMod\SPPDB\SPPDB::sppTable('loginrec') . ' SET lastaccess=? WHERE sessid=?', [date('Y-m-d H:i:s'), $sessid]);
            }
        }

        return true;
    }

    /**
     * Determine if the user has a specific permission.
     */
    public function can(string $permission, $context = null): bool
    {
        if (empty($this->permissionCache)) {
            $sessionCacheKey = '__sppauth_perms_' . ($this->id() ?? 'anon') . '__';
            $db = new \SPPMod\SPPDB\SPPDB();
            $cacheValid = false;
            
            if (\SPP\SPPSession::sessionVarExists($sessionCacheKey)) {
                $cacheData = \SPP\SPPSession::getSessionVar($sessionCacheKey);
                if (isset($cacheData['perms']) && isset($cacheData['time'])) {
                    if ($this->id() && $this->id() !== 'anon') {
                        try {
                            $res = $db->execute_query("SELECT rights_updated_at FROM ".\SPPMod\SPPDB\SPPDB::sppTable('users')." WHERE id=?", [$this->id()]);
                            if (!empty($res) && $res[0]['rights_updated_at']) {
                                $updatedAt = strtotime($res[0]['rights_updated_at']);
                                if ($cacheData['time'] >= $updatedAt) {
                                    $this->permissionCache = $cacheData['perms'];
                                    $cacheValid = true;
                                }
                            } else {
                                $this->permissionCache = $cacheData['perms'];
                                $cacheValid = true;
                            }
                        } catch (\Exception $e) {
                            $this->permissionCache = $cacheData['perms'];
                            $cacheValid = true;
                        }
                    } else {
                        $this->permissionCache = $cacheData['perms'];
                        $cacheValid = true;
                    }
                }
            }

            if (!$cacheValid) {
                $this->resolvePermissions();
                \SPP\SPPSession::setSessionVar($sessionCacheKey, [
                    'perms' => $this->permissionCache,
                    'time' => time()
                ]);
            }
        }

        $hasPerm = in_array($permission, $this->permissionCache) || in_array('*', $this->permissionCache);
        
        // ABAC Policy evaluation
        if ($hasPerm && $context !== null) {
            require_once SPP_MODULES_DIR . '/spp/sppauth/class.policyregistry.php';
            // Evaluates context attributes (e.g. department match, owner match)
            $hasPerm = \SPPMod\SPPAuth\PolicyRegistry::evaluate($this->user(), $permission, $context);
        }

        return $hasPerm;
    }

    /**
     * Resolve all permissions from groups and roles.
     */
    private function resolvePermissions(): void
    {
        $user = $this->user();
        if (!$user) {
            return;
        }

        // 1. Mandatory 'Anonymous' group permissions for everyone
        if (class_exists('\SPPMod\SPPGroup\SPPGroup')) {
            $anonGroup = new \SPPMod\SPPGroup\SPPGroup();
            try {
                $anonGroup->load('anonymous');
                if ($anonGroup->id) {
                    $this->collectGroupPermissions($anonGroup);
                }
            } catch (\Exception $e) {
            }

            // 2. Mandatory 'Authenticated' group permissions for logged in users
            if ($this->check()) {
                $authGroup = new \SPPMod\SPPGroup\SPPGroup();
                try {
                    $authGroup->load('authenticated');
                    if ($authGroup->id) {
                        $this->collectGroupPermissions($authGroup);
                    }
                } catch (\Exception $e) {
                }
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

    private function collectGroupPermissions($group): void
    {
        // Collect roles from group metadata
        $roles = (array) $group->get('roles');
        foreach ($roles as $roleSlug) {
            $this->permissionCache = array_merge($this->permissionCache, $this->resolveRolePermissions($roleSlug));
        }

        // Collect direct rights from group
        $rights = (array) $group->get('rights');
        $this->permissionCache = array_merge($this->permissionCache, $rights);
    }

    private function resolveRolePermissions(string $roleSlug): array
    {
        // In a real implementation, this would hit the DB via RBAC Role entity
        // For now, we use a registry override for performance and flexibility
        return (array) \SPP\Registry::get("rbac=>roles=>{$roleSlug}=>permissions", []);
    }

    public function user()
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $userId = \SPP\SPPSession::sessionVarExists($this->sessionKey) ? \SPP\SPPSession::getSessionVar($this->sessionKey) : null;
        if ($userId) {
            try {
                $this->user = new SPPUser($userId);
            } catch (\Exception $e) {
                // If user doesn't exist anymore, log them out
                $this->logout();
                $this->user = new AnonymousUser();
            }
        } elseif (isset($_COOKIE['spp_remember_me'])) {
            // Attempt Remember Me Auto-Login
            $token = $_COOKIE['spp_remember_me'];
            $tokenHash = hash('sha256', $token);
            
            try {
                $db = new \SPPMod\SPPDB\SPPDB();
                $result = $db->execute_query(
                    'SELECT user_id FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('remember_tokens') . ' WHERE token_hash = ? AND expires_at > ?',
                    [$tokenHash, date('Y-m-d H:i:s')]
                );
                
                if (!empty($result)) {
                    $userId = $result[0]['user_id'];
                    $user = new SPPUser($userId);
                    // Login without setting a new remember token
                    $this->login($user, false);
                    $this->user = $user;
                } else {
                    $this->user = new AnonymousUser();
                }
            } catch (\Exception $e) {
                $this->user = new AnonymousUser();
            }
        } else {
            $this->user = new AnonymousUser();
        }

        return $this->user;
    }

    public function id()
    {
        $user = $this->user();
        return $user ? $user->id : null;
    }

    public function login($user, bool $remember = false)
    {
        $id = is_object($user) ? $user->id : $user;
        
        if (!defined('SPP_NO_SESSION_REGENERATE')) {
            //try {
            //    @session_regenerate_id(true);
            //} catch (\Exception $e) {
            //    // Ignore session destruction errors
            //}
        }
        
        \SPP\SPPSession::setSessionVar($this->sessionKey, $id);
        $this->user = is_object($user) ? $user : null;

        // MFA Check
        $mfaEnabled = false;
        try {
            if ($this->user instanceof SPPUser) {
                $mfaEnabled = $this->user->get('two_factor_enabled') ?: false;
            }
        } catch (\Exception $e) { }
        
        if ($mfaEnabled) {
            \SPP\SPPSession::setSessionVar($this->mfaSessionKey, true);
            \SPP\SPPSession::setSessionVar('__sppauth_2fa_time__', time());
        }

        // Fingerprinting
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $fingerprint = hash('sha256', $ip . $ua);
        \SPP\SPPSession::setSessionVar('__sppauth_fingerprint__', $fingerprint);

        // Loginrec Device Tracking
        $sessid = session_id();
        if ($sessid) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $table = \SPPMod\SPPDB\SPPDB::sppTable('loginrec');
            $res = $db->execute_query("SELECT count(*) as cnt FROM $table WHERE sessid = ?", [$sessid]);
            $now = date('Y-m-d H:i:s');
            if (!empty($res) && (int)$res[0]['cnt'] > 0) {
                $db->execute_query("UPDATE $table SET lastaccess = ? WHERE sessid = ?", [$now, $sessid]);
            } else {
                $db->execute_query("INSERT INTO $table (sessid, uid, logintime, ipaddr, lastaccess) VALUES (?, ?, ?, ?, ?)", [$sessid, $id, $now, $ip, $now]);
            }
        }

        // Remember Me Integration
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = time() + (30 * 24 * 60 * 60); // 30 days
            
            $db = new \SPPMod\SPPDB\SPPDB();
            $sql = 'INSERT INTO ' . \SPPMod\SPPDB\SPPDB::sppTable('remember_tokens') . 
                   ' (user_id, token_hash, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))';
            
            try {
                $db->execute_query($sql, [$id, $tokenHash, $expiresAt]);
                setcookie('spp_remember_me', $token, [
                    'expires' => $expiresAt,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            } catch (\Exception $e) {
                // Ignore if remember_tokens table is missing (graceful degradation)
            }
        }

        $params = ['user' => $user];
        $evtParams = new \SPP\EventParams($params);
        \SPP\SPPEvent::fireEvent('event_spp_auth_login', $evtParams);
    }

    public function logout()
    {
        \SPP\SPPSession::unsetSessionVar($this->sessionKey);
        \SPP\SPPSession::unsetSessionVar($this->impersonateSessionKey);
        \SPP\SPPSession::unsetSessionVar($this->sudoSessionKey);
        \SPP\SPPSession::unsetSessionVar($this->mfaSessionKey);
        $this->user = null;
        $sessid = session_id();
        if ($sessid) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $db->execute_query('DELETE FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('loginrec') . ' WHERE sessid=?', [$sessid]);
        }

        if (isset($_COOKIE['spp_remember_me'])) {
            $token = $_COOKIE['spp_remember_me'];
            $tokenHash = hash('sha256', $token);
            try {
                $db = new \SPPMod\SPPDB\SPPDB();
                $db->execute_query('DELETE FROM ' . \SPPMod\SPPDB\SPPDB::sppTable('remember_tokens') . ' WHERE token_hash=?', [$tokenHash]);
            } catch (\Exception $e) {
                // Ignore if table missing
            }
            setcookie('spp_remember_me', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            unset($_COOKIE['spp_remember_me']);
        }

        \SPP\SPPSession::destroySession();

        $params = [];
        $evtParams = new \SPP\EventParams($params);
        \SPP\SPPEvent::fireEvent('event_spp_auth_logout', $evtParams);
    }

    // Phase 2: Impersonation
    public function impersonate($userId)
    {
        $currentUser = $this->id();
        if (!$currentUser) {
            throw new \Exception("Must be logged in to impersonate.");
        }
        
        \SPP\SPPSession::setSessionVar($this->impersonateSessionKey, $currentUser);
        \SPP\SPPSession::setSessionVar($this->sessionKey, $userId);
        $this->user = null; 
        AuditLogger::log('impersonate_start', $currentUser, $userId, "IP: " . ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    public function stopImpersonating()
    {
        if (\SPP\SPPSession::sessionVarExists($this->impersonateSessionKey)) {
            $originalId = \SPP\SPPSession::getSessionVar($this->impersonateSessionKey);
            \SPP\SPPSession::setSessionVar($this->sessionKey, $originalId);
            \SPP\SPPSession::unsetSessionVar($this->impersonateSessionKey);
            $this->user = null;
            AuditLogger::log('impersonate_stop', $originalId, null, "IP: " . ($_SERVER['REMOTE_ADDR'] ?? ''));
        }
    }

    public function isImpersonating(): bool
    {
        return \SPP\SPPSession::sessionVarExists($this->impersonateSessionKey);
    }

    // Phase 2: Sudo Mode
    public function requireSudo(): bool
    {
        if (!\SPP\SPPSession::sessionVarExists($this->sudoSessionKey)) {
            return false;
        }
        $expires = \SPP\SPPSession::getSessionVar($this->sudoSessionKey);
        if (time() > $expires) {
            \SPP\SPPSession::unsetSessionVar($this->sudoSessionKey);
            return false;
        }
        return true;
    }

    public function enableSudo(int $minutes = 15)
    {
        \SPP\SPPSession::setSessionVar($this->sudoSessionKey, time() + ($minutes * 60));
    }
}
