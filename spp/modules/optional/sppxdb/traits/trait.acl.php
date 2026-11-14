<?php

namespace SPPMod\SPPXDB;

use Exception;

trait XDB_Acl
{
    protected function loadACL()
    {
        $aclPath = file_exists($this->dataDir . '/acl.json')
            ? $this->dataDir . '/acl.json'
            : $this->dataDir . '/_perms.json';
        if (file_exists($aclPath)) {
            $this->permissions = json_decode(file_get_contents($aclPath), true) ?: [];
        }
    }

    public function setPermissions($table, $perms)
    {
        $this->permissions[$table] = $perms;
        file_put_contents($this->dataDir . '/acl.json', json_encode($this->permissions, JSON_PRETTY_PRINT));
        file_put_contents($this->dataDir . '/_perms.json', json_encode($this->permissions, JSON_PRETTY_PRINT));
        return $this;
    }

    protected function checkAccess($action)
    {
        if (empty($this->permissions[$this->tableName])) {
            return true;
        }
        $allowed = $this->permissions[$this->tableName][$action] ?? true;
        if (!$allowed) {
            throw new Exception("Access Denied: Action '$action' not allowed on table '{$this->tableName}'.");
        }
        return true;
    }

}
