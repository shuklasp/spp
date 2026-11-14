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
    private static function getUsersJsonPath(): string
    {
        $dir = __DIR__ . '/../data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir . '/users.json';
    }

    private static function loadJsonUsers(): array
    {
        $path = self::getUsersJsonPath();
        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    private static function saveJsonUsers(array $users): bool
    {
        $path = self::getUsersJsonPath();
        return (bool)file_put_contents($path, json_encode($users, JSON_PRETTY_PRINT));
    }

    /**
     * Retrieve all registered users across the platform with their roles, status & global admin status.
     */
    public static function getAllUsers(): array
    {
        $users = [];
        $globalAdmins = PermissionManager::getGlobalAdmins();

        // 1. Check local JSON registry
        $jsonUsers = self::loadJsonUsers();
        foreach ($jsonUsers as $u => $rec) {
            $users[$u] = $rec;
            $users[$u]['is_global_admin'] = in_array($u, $globalAdmins, true);
            $users[$u]['status'] = $rec['status'] ?? 'active';
        }

        // 2. Check XDB
        if (function_exists('get_xdb')) {
            try {
                $xdb = get_xdb('auth', 'users');
                $rows = $xdb->get();
                foreach ($rows as $row) {
                    $u = $row['username'] ?? '';
                    if ($u && !isset($users[$u])) {
                        $users[$u] = [
                            'username' => $u,
                            'display_name' => $row['display_name'] ?? $u,
                            'email' => $row['email'] ?? '',
                            'role' => $row['role'] ?? 'registered',
                            'status' => $row['status'] ?? 'active',
                            'is_global_admin' => in_array($u, $globalAdmins, true),
                            'created_at' => $row['created_at'] ?? time(),
                        ];
                    }
                }
            } catch (\Exception $e) {}
        }

        // 3. Check XML user store
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
                            'status' => (string) ($row->status ?? 'active'),
                            'is_global_admin' => in_array($u, $globalAdmins, true),
                            'created_at' => (int) ($row->created_at ?? time()),
                        ];
                    }
                }
            }
        }

        // 4. Fallback: Ensure 'admin' and all global admins exist
        foreach ($globalAdmins as $admin) {
            if (!isset($users[$admin])) {
                $users[$admin] = [
                    'username' => $admin,
                    'display_name' => ucfirst($admin),
                    'email' => $admin . '@example.local',
                    'role' => 'admin',
                    'status' => 'active',
                    'is_global_admin' => true,
                    'created_at' => time(),
                ];
            } else {
                $users[$admin]['is_global_admin'] = true;
                $users[$admin]['status'] = $users[$admin]['status'] ?? 'active';
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

        // Always store into JSON registry (primary storage, works everywhere)
        $jsonUsers = self::loadJsonUsers();
        $jsonUsers[$username] = [
            'username' => $username,
            'display_name' => $displayName,
            'email' => $email,
            'role' => $role,
            'created_at' => time(),
            'status' => 'active',
            'password' => $hashed
        ];
        self::saveJsonUsers($jsonUsers);

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

    /**
     * Get single user record.
     */
    public static function getUser(string $username): ?array
    {
        $all = self::getAllUsers();
        foreach ($all as $u) {
            if (strcasecmp($u['username'], $username) === 0) {
                return $u;
            }
        }
        return null;
    }

    /**
     * Moderate a user account's status (active, suspended, banned).
     */
    public static function setUserStatus(string $username, string $status, ?string $reason = null): array
    {
        $status = strtolower($status);
        if (!in_array($status, ['active', 'suspended', 'banned'], true)) {
            return ['success' => false, 'message' => "Invalid status '{$status}'. Allowed: active, suspended, banned."];
        }

        if (strcasecmp($username, 'admin') === 0 && $status !== 'active') {
            return ['success' => false, 'message' => 'Root admin account cannot be suspended or banned.'];
        }

        $jsonUsers = self::loadJsonUsers();
        $user = $jsonUsers[$username] ?? self::getUser($username) ?? [
            'username' => $username,
            'display_name' => ucfirst($username),
            'email' => '',
            'role' => 'registered',
            'created_at' => time()
        ];

        $user['status'] = $status;
        $user['moderation'] = [
            'status' => $status,
            'reason' => $reason,
            'moderated_at' => time()
        ];
        $jsonUsers[$username] = $user;
        self::saveJsonUsers($jsonUsers);

        return [
            'success' => true,
            'message' => "User '{$username}' status set to '{$status}'." . ($reason ? " Reason: {$reason}" : '')
        ];
    }

    /**
     * Moderate a user's platform role.
     */
    public static function setUserRole(string $username, string $role): array
    {
        $role = strtolower(trim($role));
        if (empty($role)) {
            return ['success' => false, 'message' => 'Role cannot be empty.'];
        }

        $jsonUsers = self::loadJsonUsers();
        $user = $jsonUsers[$username] ?? self::getUser($username) ?? [
            'username' => $username,
            'display_name' => ucfirst($username),
            'email' => '',
            'status' => 'active',
            'created_at' => time()
        ];

        $user['role'] = $role;
        $jsonUsers[$username] = $user;
        self::saveJsonUsers($jsonUsers);

        return ['success' => true, 'message' => "User '{$username}' role updated to '{$role}'."];
    }

    /**
     * Administrative password reset for user account.
     */
    public static function resetPassword(string $username, string $newPassword): array
    {
        if (strlen($newPassword) < 4) {
            return ['success' => false, 'message' => 'Password must be at least 4 characters long.'];
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $jsonUsers = self::loadJsonUsers();
        $user = $jsonUsers[$username] ?? self::getUser($username) ?? [
            'username' => $username,
            'display_name' => ucfirst($username),
            'email' => '',
            'role' => 'registered',
            'status' => 'active',
            'created_at' => time()
        ];

        $user['password'] = $hashed;
        $jsonUsers[$username] = $user;
        self::saveJsonUsers($jsonUsers);

        return ['success' => true, 'message' => "Password reset successfully for user '{$username}'."];
    }
}
