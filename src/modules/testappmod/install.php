<?php
/**
 * install.php
 * 
 * Executed ONLY once when the module is installed via `php spp.php module:install testappmod`.
 * Use this file to:
 * - Seed initial database rows
 * - Create necessary storage directories
 * - Set up third-party webhooks
 */

$db = \SPP\Core\ModuleInstaller::getDb();
// Example: $db->execute_query("INSERT INTO settings (key, val) VALUES (?, ?)", ['testappmod_active', '1']);