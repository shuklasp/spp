<?php
namespace SPPMod\SPPAuth\Migrations;

use SPPMod\SPPDB\Migration\SPPMigration;

class SppAuthV2Architecture extends SPPMigration
{

    public function up(): void
    {
        $isSqlite = ($this->db->getDriver() === 'sqlite');
        
        if ($isSqlite) {
            // 1. login_attempts
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                username VARCHAR(255) NOT NULL,
                attempts INT DEFAULT 1,
                last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $this->db->execute_query("CREATE INDEX IF NOT EXISTS idx_ip_user ON spp_login_attempts (ip_address, username)");

            // 2. personal_access_tokens
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_personal_access_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token VARCHAR(64) NOT NULL UNIQUE,
                userid VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL
            )");

            // 3. oauth_providers
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_oauth_providers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id VARCHAR(255) NOT NULL,
                provider VARCHAR(50) NOT NULL,
                provider_id VARCHAR(255) NOT NULL,
                UNIQUE (provider, provider_id)
            )");

            // 4. sppauth_audit_log
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_sppauth_audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type VARCHAR(100) NOT NULL,
                user_id VARCHAR(255) NULL,
                target_id VARCHAR(255) NULL,
                details TEXT,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // 5. magic_links
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_magic_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id VARCHAR(255) NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL
            )");

            // 6. webauthn_credentials
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_webauthn_credentials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id VARCHAR(255) NOT NULL,
                credential_id TEXT NOT NULL,
                public_key TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // 7. OAuth IdP Tables
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_oauth_clients (
                id VARCHAR(100) PRIMARY KEY,
                secret VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                redirect_uri TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_oauth_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id VARCHAR(100) NOT NULL,
                user_id VARCHAR(255) NOT NULL,
                access_token VARCHAR(100) NOT NULL UNIQUE,
                refresh_token VARCHAR(100) NULL UNIQUE,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // 8. users Table Alterations
            try { $this->db->execute_query("ALTER TABLE spp_users ADD COLUMN two_factor_secret VARCHAR(255) NULL"); } catch (\Exception $e) {}
            try { $this->db->execute_query("ALTER TABLE spp_users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0"); } catch (\Exception $e) {}
            try { $this->db->execute_query("ALTER TABLE spp_users ADD COLUMN password_updated_at TIMESTAMP NULL"); } catch (\Exception $e) {}
            try { $this->db->execute_query("ALTER TABLE spp_users ADD COLUMN rights_updated_at TIMESTAMP NULL"); } catch (\Exception $e) {}

            try { $this->db->execute_query("ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(255) NULL"); } catch (\Exception $e) {}
            try { $this->db->execute_query("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0"); } catch (\Exception $e) {}
            try { $this->db->execute_query("ALTER TABLE users ADD COLUMN password_updated_at TIMESTAMP NULL"); } catch (\Exception $e) {}
            try { $this->db->execute_query("ALTER TABLE users ADD COLUMN rights_updated_at TIMESTAMP NULL"); } catch (\Exception $e) {}

            // 9. loginrec Table Alterations
            try { $this->db->execute_query("ALTER TABLE spp_loginrec ADD COLUMN user_agent TEXT NULL"); } catch (\Exception $e) {}
            try { $this->db->execute_query("ALTER TABLE loginrec ADD COLUMN user_agent TEXT NULL"); } catch (\Exception $e) {}
        } else {
            // MySQL Dialect
            // 1. login_attempts
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                username VARCHAR(255) NOT NULL,
                attempts INT DEFAULT 1,
                last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ip_user (ip_address, username)
            )");

            // 2. personal_access_tokens
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_personal_access_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(64) NOT NULL UNIQUE,
                userid VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL
            )");

            // 3. oauth_providers
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_oauth_providers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                provider VARCHAR(50) NOT NULL,
                provider_id VARCHAR(255) NOT NULL,
                UNIQUE KEY unique_provider (provider, provider_id)
            )");

            // 4. sppauth_audit_log
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_sppauth_audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(100) NOT NULL,
                user_id VARCHAR(255) NULL,
                target_id VARCHAR(255) NULL,
                details TEXT,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // 5. magic_links
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_magic_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL
            )");

            // 6. webauthn_credentials
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_webauthn_credentials (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                credential_id TEXT NOT NULL,
                public_key TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // 7. OAuth IdP Tables
            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_oauth_clients (
                id VARCHAR(100) PRIMARY KEY,
                secret VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                redirect_uri TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $this->db->execute_query("CREATE TABLE IF NOT EXISTS spp_oauth_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id VARCHAR(100) NOT NULL,
                user_id VARCHAR(255) NOT NULL,
                access_token VARCHAR(100) NOT NULL UNIQUE,
                refresh_token VARCHAR(100) NULL UNIQUE,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // 8. users Table Alterations
            try {
                $this->db->execute_query("ALTER TABLE spp_users 
                    ADD COLUMN two_factor_secret VARCHAR(255) NULL,
                    ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0,
                    ADD COLUMN password_updated_at TIMESTAMP NULL,
                    ADD COLUMN rights_updated_at TIMESTAMP NULL");
            } catch (\Exception $e) {
                // Columns might exist
            }

            try {
                // Fallback if the legacy users table without prefix is used directly
                $this->db->execute_query("ALTER TABLE users 
                    ADD COLUMN two_factor_secret VARCHAR(255) NULL,
                    ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0,
                    ADD COLUMN password_updated_at TIMESTAMP NULL,
                    ADD COLUMN rights_updated_at TIMESTAMP NULL");
            } catch (\Exception $e) {
            }

            // 9. loginrec Table Alterations
            try {
                $this->db->execute_query("ALTER TABLE spp_loginrec ADD COLUMN user_agent TEXT NULL");
            } catch (\Exception $e) {
            }
            try {
                $this->db->execute_query("ALTER TABLE loginrec ADD COLUMN user_agent TEXT NULL");
            } catch (\Exception $e) {
            }
        }
    }

    public function down(): void
    {
        $this->db->execute_query("DROP TABLE IF EXISTS spp_login_attempts");
        $this->db->execute_query("DROP TABLE IF EXISTS spp_personal_access_tokens");
        $this->db->execute_query("DROP TABLE IF EXISTS spp_oauth_providers");
        $this->db->execute_query("DROP TABLE IF EXISTS spp_sppauth_audit_log");
        $this->db->execute_query("DROP TABLE IF EXISTS spp_magic_links");
        $this->db->execute_query("DROP TABLE IF EXISTS spp_webauthn_credentials");
        $this->db->execute_query("DROP TABLE IF EXISTS spp_oauth_clients");
        $this->db->execute_query("DROP TABLE IF EXISTS spp_oauth_tokens");

        $isSqlite = ($this->db->getDriver() === 'sqlite');
        
        if (!$isSqlite) {
            try {
                $this->db->execute_query("ALTER TABLE spp_users 
                    DROP COLUMN two_factor_secret,
                    DROP COLUMN two_factor_enabled,
                    DROP COLUMN password_updated_at,
                    DROP COLUMN rights_updated_at");
            } catch (\Exception $e) {
            }
            try {
                $this->db->execute_query("ALTER TABLE users 
                    DROP COLUMN two_factor_secret,
                    DROP COLUMN two_factor_enabled,
                    DROP COLUMN password_updated_at,
                    DROP COLUMN rights_updated_at");
            } catch (\Exception $e) {
            }

            try {
                $this->db->execute_query("ALTER TABLE spp_loginrec DROP COLUMN user_agent");
            } catch (\Exception $e) {
            }
            try {
                $this->db->execute_query("ALTER TABLE loginrec DROP COLUMN user_agent");
            } catch (\Exception $e) {
            }
        }
    }
}
