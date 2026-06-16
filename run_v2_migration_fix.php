<?php
require 'spp/sppinit.php';
$db = new SPPMod\SPPDB\SPPDB();

function getTableName($name) {
    return SPPMod\SPPDB\SPPDB::sppTable($name);
}

$db->execute_query('CREATE TABLE IF NOT EXISTS ' . getTableName('login_attempts') . ' (id INT AUTO_INCREMENT PRIMARY KEY, ip_address VARCHAR(45) NOT NULL, username VARCHAR(255) NOT NULL, attempts INT DEFAULT 1, last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_ip_user (ip_address, username))');
$db->execute_query('CREATE TABLE IF NOT EXISTS ' . getTableName('personal_access_tokens') . ' (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) DEFAULT "API Key", token VARCHAR(64) NOT NULL UNIQUE, userid VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, expires_at TIMESTAMP NULL)');
$db->execute_query('CREATE TABLE IF NOT EXISTS ' . getTableName('oauth_providers') . ' (id INT AUTO_INCREMENT PRIMARY KEY, user_id VARCHAR(255) NOT NULL, provider VARCHAR(50) NOT NULL, provider_id VARCHAR(255) NOT NULL, UNIQUE KEY unique_provider (provider, provider_id))');
$db->execute_query('CREATE TABLE IF NOT EXISTS ' . getTableName('sppauth_audit_log') . ' (id INT AUTO_INCREMENT PRIMARY KEY, event_type VARCHAR(100) NOT NULL, user_id VARCHAR(255) NULL, target_id VARCHAR(255) NULL, details TEXT, ip_address VARCHAR(45) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
$db->execute_query('CREATE TABLE IF NOT EXISTS ' . getTableName('magic_links') . ' (id INT AUTO_INCREMENT PRIMARY KEY, user_id VARCHAR(255) NOT NULL, token VARCHAR(64) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, expires_at TIMESTAMP NULL)');
$db->execute_query('CREATE TABLE IF NOT EXISTS ' . getTableName('webauthn_credentials') . ' (id INT AUTO_INCREMENT PRIMARY KEY, user_id VARCHAR(255) NOT NULL, credential_id TEXT NOT NULL, public_key TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
$db->execute_query('CREATE TABLE IF NOT EXISTS ' . getTableName('oauth_clients') . ' (id VARCHAR(100) PRIMARY KEY, secret VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, redirect_uri TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
$db->execute_query('CREATE TABLE IF NOT EXISTS ' . getTableName('oauth_tokens') . ' (id INT AUTO_INCREMENT PRIMARY KEY, client_id VARCHAR(100) NOT NULL, user_id VARCHAR(255) NOT NULL, access_token VARCHAR(100) NOT NULL UNIQUE, refresh_token VARCHAR(100) NULL UNIQUE, expires_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');

echo "Fixed Migration done.\n";
