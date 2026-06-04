<?php

namespace App\Default\Migrations;

use SPP\Core\Migration;

class Migration_20260531_145806_create_auth_tables extends Migration
{
    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function up(): void
    {
        $users = \SPPMod\SPPDB\SPPDB::sppTable('users');
        $roles = \SPPMod\SPPDB\SPPDB::sppTable('roles');
        $rights = \SPPMod\SPPDB\SPPDB::sppTable('rights');
        $userroles = \SPPMod\SPPDB\SPPDB::sppTable('userroles');
        $roleright = \SPPMod\SPPDB\SPPDB::sppTable('roleright');

        $this->executeSql("
            CREATE TABLE IF NOT EXISTS {$users} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(255) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                status VARCHAR(50) DEFAULT 'active',
                created_at DATETIME,
                updated_at DATETIME
            )
        ");

        $this->executeSql("
            CREATE TABLE IF NOT EXISTS {$roles} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL UNIQUE
            )
        ");

        $this->executeSql("
            CREATE TABLE IF NOT EXISTS {$rights} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL UNIQUE
            )
        ");

        $this->executeSql("
            CREATE TABLE IF NOT EXISTS {$userroles} (
                userid INTEGER NOT NULL,
                roleid INTEGER NOT NULL,
                PRIMARY KEY (userid, roleid)
            )
        ");

        $this->executeSql("
            CREATE TABLE IF NOT EXISTS {$roleright} (
                roleid INTEGER NOT NULL,
                rightid INTEGER NOT NULL,
                PRIMARY KEY (roleid, rightid)
            )
        ");
    }

    public function down(): void
    {
        $users = \SPPMod\SPPDB\SPPDB::sppTable('users');
        $roles = \SPPMod\SPPDB\SPPDB::sppTable('roles');
        $rights = \SPPMod\SPPDB\SPPDB::sppTable('rights');
        $userroles = \SPPMod\SPPDB\SPPDB::sppTable('userroles');
        $roleright = \SPPMod\SPPDB\SPPDB::sppTable('roleright');

        $this->executeSql("DROP TABLE IF EXISTS {$roleright}");
        $this->executeSql("DROP TABLE IF EXISTS {$userroles}");
        $this->executeSql("DROP TABLE IF EXISTS {$rights}");
        $this->executeSql("DROP TABLE IF EXISTS {$roles}");
        $this->executeSql("DROP TABLE IF EXISTS {$users}");
    }
}
