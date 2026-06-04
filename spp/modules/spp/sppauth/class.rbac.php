<?php

namespace SPPMod\SPPAuth;

use SPPMod\SPPEntity\SPPEntity;

/**
 * class Role
 * Represents a set of permissions.
 */
class Role extends SPPEntity
{
    protected $source = 'database';

    public function define_attributes()
    {
        return [
            'name' => 'varchar(100)',
            'slug' => 'varchar(100)',
            'description' => 'text'
        ];
    }

    public function permissions()
    {
        // Returns a list of permission slugs associated with this role
        $perms = \SPP\DB::query("SELECT p.slug FROM spp_permissions p 
                                 JOIN spp_role_permissions rp ON p.id = rp.permission_id 
                                 WHERE rp.role_id = ?", [$this->id]);
        return array_column($perms, 'slug');
    }
}

/**
 * class Permission
 * Represents a single atomic right.
 */
class Permission extends SPPEntity
{
    protected $source = 'database';

    public function define_attributes()
    {
        return [
            'name' => 'varchar(100)',
            'slug' => 'varchar(100)',
            'description' => 'text'
        ];
    }
}
