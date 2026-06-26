<?php
namespace SPPMod\Sppauth\Migrations;

use SPPMod\SPPDB\Migration\SPPMigration;

class ConsolidateIdentityTables extends SPPMigration
{

    public function up(): void
    {
        // 1. Rename existing users table to spp_users if it isn't already
        $this->db->exec_squery("CREATE TABLE IF NOT EXISTS spp_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // 2. Create Unified Roles Table
        $this->db->exec_squery("CREATE TABLE IF NOT EXISTS spp_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            description TEXT
        )");

        // 3. User to Roles mapping
        $this->db->exec_squery("CREATE TABLE IF NOT EXISTS spp_user_roles (
            user_id INT,
            role_id INT,
            PRIMARY KEY(user_id, role_id)
        )");

        // 4. Migrate Profiles data (from spp_profiles to spp_users or a unified profile JSON column)
        // Since profile fields can vary, adding a JSON column to spp_users is the modern approach
        try {
            $this->db->exec_squery("ALTER TABLE spp_users ADD COLUMN profile_data JSON");
        } catch (\Exception $e) {
            // Column might exist
        }

        // Port legacy data if the tables exist
        try {
            $oldProfiles = $this->db->exec_squery("SELECT * FROM spp_profiles");
            foreach ($oldProfiles as $profile) {
                $userId = $profile['user_id'] ?? null;
                if ($userId) {
                    $json = json_encode($profile);
                    $this->db->exec_squery("UPDATE spp_users SET profile_data = ? WHERE id = ?", [$json, $userId]);
                }
            }
        } catch (\Exception $e) {
            // spp_profiles might not exist, ignore
        }

        try {
            $oldGroups = $this->db->exec_squery("SELECT * FROM spp_groups");
            foreach ($oldGroups as $group) {
                // Insert as Role
                $this->db->exec_squery("INSERT IGNORE INTO spp_roles (name, description) VALUES (?, ?)", [$group['name'], $group['description'] ?? '']);
            }
        } catch (\Exception $e) {
            // spp_groups might not exist, ignore
        }
    }

    public function down(): void
    {
        // Rollback is complex if we merged tables, but for testing we can drop the new ones
        $this->db->exec_squery("DROP TABLE IF EXISTS spp_user_roles");
        $this->db->exec_squery("DROP TABLE IF EXISTS spp_roles");
        try {
            $this->db->exec_squery("ALTER TABLE spp_users DROP COLUMN profile_data");
        } catch (\Exception $e) {
        }
    }
}
