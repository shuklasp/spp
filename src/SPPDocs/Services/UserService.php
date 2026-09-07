<?php

namespace App\SPPDocs\Services;

/**
 * UserService
 * Manages user governance, directory listing, user creation, and global admin toggles.
 */
class UserService
{
    /**
     * Retrieve all registered users across the platform with their roles & global admin status.
     */
    public static function getAllUsers(): array
    {
        $users = [];
        $globalAdmins = PermissionManager::getGlobalAdmins();

        // 1. Check XDB
        if (function_exists('get_xdb')) {
            try {
                $xdb = get_xdb('auth', 'users');
                $rows = $xdb->get();
                foreach ($rows as $row) {
                    $u = $row['username'] ?? '';
                    if ($u) {
                        $users[$u] = [
                            'username' => $u,
                            'display_name' => $row['display_name'] ?? $u,
                            'email' => $row['email'] ?? '',
                            'role' => $row['role'] ?? 'registered',
                            'is_global_admin' => in_array($u, $globalAdmins, true),
                            'created_at' => $row['created_at'] ?? time(),
                        ];
                    }
                }
            } catch (\Exception $e) {}
        }

        // 2. Check XML user store
        $usersFile = dirname(SPP_BASE_DIR) . '/spp/data/xdb/users/users.xml';
        if (file_exists($usersFile)) {
            $xml = @simplexml_load_file($usersFile);
            if ($xml) {
                foreach ($xml->row as $row) {
                    $u = (string) $row->username;
                    if ($u && !isset($users[$u])) {
                        $users[$u] = [
                            'username' => $u,
                            'display_name' => (string) ($row->display_name ?? $u),
                            'email' => (string) ($row->email ?? ''),
                            'role' => (string) ($row->role ?? 'registered'),
                            'is_global_admin' => in_array($u, $globalAdmins, true),
                            'created_at' => (int) ($row->created_at ?? time()),
                        ];
                    }
                }
            }
        }

        // 3. Fallback: Ensure 'admin' and all global admins exist
        foreach ($globalAdmins as $admin) {
            if (!isset($users[$admin])) {
                $users[$admin] = [
                    'username' => $admin,
                    'display_name' => ucfirst($admin),
                    'email' => $admin . '@satyalab.local',
                    'role' => 'admin',
                    'is_global_admin' => true,
                    'created_at' => time(),
                ];
            } else {
                $users[$admin]['is_global_admin'] = true;
            }
        }

        return array_values($users);
    }

    /**
     * Create a new platform user account.
     */
    public static function createUser(string $username, string $password, string $displayName = '', string $email = '', bool $isGlobalAdmin = false): array
    {
        $username = strtolower(trim(preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username)));
        if (empty($username)) {
            return ['success' => false, 'message' => 'Username contains invalid characters or is empty.'];
        }

        if (strlen($password) < 4) {
            return ['success' => false, 'message' => 'Password must be at least 4 characters long.'];
        }

        $all = self::getAllUsers();
        foreach ($all as $u) {
            if (strcasecmp($u['username'], $username) === 0) {
                return ['success' => false, 'message' => "User '{$username}' already exists."];
            }
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $role = $isGlobalAdmin ? 'admin' : 'registered';
        $displayName = trim($displayName) ?: ucfirst($username);

        // Store into XDB if available
        if (function_exists('get_xdb')) {
            try {
                $xdb = get_xdb('auth', 'users');
                $xdb->insert([
                    'username' => $username,
                    'password' => $hashed,
                    'display_name' => $displayName,
                    'email' => $email,
                    'role' => $role,
                    'created_at' => time()
                ]);
            } catch (\Exception $e) {}
        }

        // Also append to XML user store if it exists
        $usersFile = dirname(SPP_BASE_DIR) . '/spp/data/xdb/users/users.xml';
        if (file_exists($usersFile)) {
            $xml = @simplexml_load_file($usersFile);
            if ($xml) {
                $row = $xml->addChild('row');
                $row->addChild('username', htmlspecialchars($username));
                $row->addChild('password', $hashed);
                $row->addChild('display_name', htmlspecialchars($displayName));
                $row->addChild('email', htmlspecialchars($email));
                $row->addChild('role', $role);
                $row->addChild('created_at', time());
                $xml->asXML($usersFile);
            }
        }

        if ($isGlobalAdmin) {
            $admins = PermissionManager::getGlobalAdmins();
            $admins[] = $username;
            PermissionManager::setGlobalAdmins($admins);
        }

        return ['success' => true, 'message' => "User '{$username}' created successfully."];
    }

    /**
     * Toggle the Global Super Admin status for a given user.
     * Prevents removing the last remaining Global Super Admin.
     */
    public static function toggleGlobalAdmin(string $username, bool $makeAdmin): array
    {
        $admins = PermissionManager::getGlobalAdmins();

        if ($makeAdmin) {
            if (!in_array($username, $admins, true)) {
                $admins[] = $username;
                PermissionManager::setGlobalAdmins($admins);
            }
            return ['success' => true, 'message' => "User '{$username}' is now a Global Super Admin."];
        } else {
            // Check fail-safe: cannot revoke last remaining global admin
            $remaining = array_values(array_filter($admins, fn($a) => $a !== $username));
            if (empty($remaining)) {
                return ['success' => false, 'message' => 'Cannot revoke the last remaining Global Super Admin.'];
            }
            PermissionManager::setGlobalAdmins($remaining);
            return ['success' => true, 'message' => "Global Super Admin privileges revoked for '{$username}'."];
        }
    }
}
