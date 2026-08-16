<?php

namespace SPPMod\SPPAuth;

use SPPMod\SPPDB\SPPEntity;
use SPP\Exceptions\UserNotFoundException;
use SPPMod\SPPDB\SPPDB;

/**
 * Class SPPUser
 *
 * Manages user entities within the SPP framework. Handles authentication,
 * profile retrieval, and role management using the modernized SPPEntity architecture.
 */
class SPPUser extends SPPEntity
{
    /** @var array $role_ids Internal storage for many-to-many role IDs */
    protected $role_ids = [];

    /** @var array $rights Non-persistent storage for derived permissions */
    protected $rights = [];

    /**
     * Map to existing table name.
     */
    public function getTable(): string
    {
        $metaTable = static::getMetadata('table');
        if ($metaTable && $metaTable !== 'users') {
            return $metaTable;
        }
        return \SPPMod\SPPDB\SPPDB::sppTable('users');
    }

    /**
     * Entity constructor. Supports loading by username or ID.
     */
    public function __construct($unm = null)
    {
        parent::__construct(); // Initialize metadata and _values
        if ($unm !== null && !is_numeric($unm)) {
            try {
                $this->loadByUsername($unm);
            } catch (\Exception $e) {
                throw new UserNotFoundException("User '{$unm}' not found. Original error: " . $e->getMessage());
            }
        } elseif ($unm !== null) {
            $this->load($unm);
        }
    }

    /**
     * Returns the internal numeric ID of the user.
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Checks if the user account is active and enabled for login.
     * @return bool
     */
    public function isEnabled()
    {
        return ($this->status === 'active');
    }

    /**
     * Property getter with backward compatibility for legacy auth keys.
     * @param string $prop
     * @return mixed
     */
    public function get($prop)
    {
        if ($prop === 'UserId') {
            return $this->id;
        }
        if ($prop === 'UserName') {
            return $this->username;
        }
        if ($prop === 'rights') {
            return $this->rights;
        }
        return parent::get($prop);
    }

    /**
     * Hook called after entity data is loaded from the primary table.
     * Fetches multi-role assignments from the pivot table.
     */
    public function after_load()
    {
        $db = new SPPDB();
        $this->role_ids = [];

        // Pivot table resiliency during bootstrap
        if ($db->tableExists('userroles')) {
            $sql = 'SELECT roleid FROM ' . SPPDB::sppTable('userroles') . ' WHERE userid=?';
            $res = $db->execute_query($sql, [$this->id]);
            $this->role_ids = array_column($res, 'roleid');
        }

        // Populate 'rights' for BC
        $this->populateRights();
    }

    /**
     * Synchronizes rights from all assigned roles.
     */
    private function populateRights()
    {
        if (empty($this->role_ids)) {
            $this->rights = [];
            return;
        }

        $db = new SPPDB();
        if (!$db->tableExists('rights') || !$db->tableExists('roleright')) {
            $this->rights = [];
            return;
        }

        $placeholders = implode(',', array_fill(0, count($this->role_ids), '?'));
        $sql = "SELECT DISTINCT rt.name FROM " . SPPDB::sppTable('rights') . " rt 
                JOIN " . SPPDB::sppTable('roleright') . " rr ON rt.id = rr.rightid 
                WHERE rr.roleid IN ({$placeholders})";

        $res = $db->execute_query($sql, $this->role_ids);
        $this->rights = array_column($res, 'name');
    }

    /**
     * Hook called before database persistence.
     * Handles password hashing if a plaintext value is provided.
     */
    public function before_save()
    {
        if (isset($this->_values['password']) && !empty($this->_values['password'])) {
            $val = $this->_values['password'];
            // Hash only if it's not already a bcrypt hash
            if (strpos($val, '$2y$') !== 0) {
                $hash = password_hash($val, PASSWORD_DEFAULT);
            } else {
                $hash = $val;
            }
            
            $cols = $this->getAttributes();
            if (array_key_exists('password_hash', $cols)) {
                $this->_values['password_hash'] = $hash;
                unset($this->_values['password']);
            } else {
                $this->_values['password'] = $hash;
            }
        }

        if (empty($this->id)) {
            $this->_values['created_at'] = date('Y-m-d H:i:s');
        }
        $this->_values['updated_at'] = date('Y-m-d H:i:s');
    }

    /**
     * Hook called after successful database persistence.
     * Synchronizes role assignments to the pivot table.
     */
    public function after_save()
    {
        if ($this->role_ids !== null) {
            $db = new SPPDB();
            if (!$db->tableExists('userroles')) {
                return;
            }

            // 1. Wipe current assignments
            $sql = 'DELETE FROM ' . SPPDB::sppTable('userroles') . ' WHERE userid=?';
            $db->execute_query($sql, [$this->id]);

            // 2. Re-insert new assignments
            foreach ($this->role_ids as $roleId) {
                $db->insertValues('userroles', ['userid' => $this->id, 'roleid' => $roleId]);
            }
        }
    }

    /**
     * Load user data by username.
     */
    public function loadByUsername($unm)
    {
        $db = new SPPDB();
        $sql = 'SELECT id FROM ' . SPPDB::sppTable('users') . ' WHERE username=?';
        $res = $db->execute_query($sql, [$unm]);
        if (count($res) > 0) {
            $this->load($res[0]['id']);
        } else {
            throw new UserNotFoundException("Username '{$unm}' not found.");
        }
    }

    /**
     * Set the assigned roles for the user.
     * @param array $roleIds
     */
    public function setRoles(array $roleIds)
    {
        $this->role_ids = array_map('intval', $roleIds);
    }

    /**
     * Returns assigned role IDs.
     */
    public function getRoles(): array
    {
        return $this->role_ids;
    }

    /**
     * Verify a plaintext password.
     */
    public function verifyPassword($passwd)
    {
        try {
            $hash = $this->password ?? '';
        } catch (\Exception $e) {
            $hash = '';
        }

        try {
            $hash2 = $this->password_hash ?? '';
        } catch (\Exception $e) {
            $hash2 = '';
        }

        $hash = $hash ?: $hash2;

        if (empty($hash)) {
            file_put_contents(SPP_BASE_DIR . '/var/logs/my_auth_debug.log', "verifyPassword failed: hash is empty. Attributes: " . print_r($this->getAttributes(), true) . "\n", FILE_APPEND);
            return false;
        }

        $isActive = true;
        try {
            $vals = $this->getValues();
            if (array_key_exists('active', $vals)) {
                $isActive = $vals['active'];
            } elseif (array_key_exists('status', $vals)) {
                $isActive = ($vals['status'] === 'active' || $vals['status'] === '1');
            } elseif (array_key_exists('enabled', $vals)) {
                $isActive = ($vals['enabled'] === 'Y' || $vals['enabled'] === '1' || $vals['enabled'] === true);
            }
        } catch (\Exception $e) { }

        if (!$isActive) {
            file_put_contents(SPP_BASE_DIR . '/var/logs/my_auth_debug.log', "verifyPassword failed: not active\n", FILE_APPEND);
            return false;
        }

        if (strpos($hash, '$2y$') === 0) {
            $valid = password_verify($passwd, $hash);
            file_put_contents(SPP_BASE_DIR . '/var/logs/my_auth_debug.log', "verifyPassword bcrypt: $valid for hash $hash\n", FILE_APPEND);
            return $valid;
        }

        // Add logging for fallback checks
        file_put_contents(SPP_BASE_DIR . '/var/logs/my_auth_debug.log', "verifyPassword fallback reached. hash: $hash\n", FILE_APPEND);

        return false;
    }

    /**
     * Checks if user has a specific right.
     */
    public function hasRight($rt)
    {
        return in_array($rt, $this->rights);
    }

    /**
     * Static helper for authentication.
     */
    public static function verifyUserPassword($uname, $passwd)
    {
        try {
            $user = new self($uname);
            return $user->verifyPassword($passwd);
        } catch (\Exception $e) {
            file_put_contents(SPP_BASE_DIR . '/../var/logs/my_auth_debug.log', "verifyUserPassword EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    /**
     * Static helper for existence check.
     */
    public static function userExists($uname)
    {
        $db = new SPPDB();
        $res = $db->execute_query('SELECT id FROM ' . SPPDB::sppTable('users') . ' WHERE username=?', [$uname]);
        return count($res) > 0;
    }

    /**
     * Centralized orchestration to create or update a user.
     * Ensures identical logic for CLI and UI.
     */
    public static function saveUserInfo(array $data)
    {
        $id = $data['id'] ?? null;
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $status = $data['status'] ?? 'active';
        $roleIds = $data['role_ids'] ?? [];

        if (!empty($id)) {
            $user = new self($id);
        } else {
            $user = new self();
            if (empty($username)) {
                throw new \Exception("Username is required for new accounts.");
            }
            $user->username = $username;
        }

        $user->email = $email;
        if (!empty($password)) {
            $user->password = $password;
        }
        $user->status = $status;

        if (is_array($roleIds)) {
            $user->setRoles($roleIds);
        }

        $user->save();
        return $user->id;
    }

    /**
     * Shorthand to assign a role to a user.
     */
    public static function assignRole(int $userId, int $roleId)
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('userroles');
        $db->execute_query("DELETE FROM {$table} WHERE userid=? AND roleid=?", [$userId, $roleId]);
        $db->insertValues('userroles', ['userid' => $userId, 'roleid' => $roleId]);
    }

    /**
     * Shorthand to remove a role from a user.
     */
    public static function unassignRole(int $userId, int $roleId)
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query("DELETE FROM " . \SPPMod\SPPDB\SPPDB::sppTable('userroles') . " WHERE userid=? AND roleid=?", [$userId, $roleId]);
    }
}
