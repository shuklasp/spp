<?php
/**
 * uninstall.php
 * 
 * Executed ONLY once when the module is uninstalled via `php spp.php module:uninstall testappmod`.
 * Use this file to:
 * - Clean up physical files or directories
 * - Remove third-party webhooks
 * - (Optional) Remove database tables, though retaining them prevents data loss.
 */

$db = \SPP\Core\ModuleInstaller::getDb();
// Example: $db->execute_query("DELETE FROM settings WHERE key = ?", ['testappmod_active']);